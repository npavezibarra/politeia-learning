<?php
/**
 * Utility for email assets (SVG to JPG conversion).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Core_Email_Assets {

    /**
     * Converts an SVG logo to JPG for email compatibility.
     * Caches the result in the uploads folder.
     *
     * @param string $logo_url Original logo URL.
     * @return string Rasterized logo URL or original if not SVG or conversion fails.
     */
    public static function get_rasterized_logo_url($logo_url) {
        if (empty($logo_url)) {
            return '';
        }

        // Only process SVGs
        $path_info = pathinfo($logo_url);
        if (!isset($path_info['extension']) || strtolower($path_info['extension']) !== 'svg') {
            return $logo_url;
        }

        $upload_dir = wp_upload_dir();
        $cache_dir_name = 'pl-email-cache';
        $cache_path = $upload_dir['basedir'] . '/' . $cache_dir_name;
        $cache_url = $upload_dir['baseurl'] . '/' . $cache_dir_name;

        // Ensure cache directory exists
        if (!file_exists($cache_path)) {
            wp_mkdir_p($cache_path);
        }

        // Generate unique filename based on the URL
        $filename = 'logo-' . md5($logo_url) . '.jpg';
        $dest_file = $cache_path . '/' . $filename;
        $dest_url = $cache_url . '/' . $filename;

        // If cached file exists, return it
        if (file_exists($dest_file)) {
            return $dest_url;
        }

        // Try to convert using Imagick
        if (class_exists('Imagick')) {
            try {
                // Determine source path
                $attachment_id = attachment_url_to_postid($logo_url);
                $source_path = $attachment_id ? get_attached_file($attachment_id) : '';
                $input_source = ($source_path && file_exists($source_path)) ? $source_path : $logo_url;

                $im = new Imagick();
                
                // 1. Moderate density (300 DPI) to avoid "thick font" artifacts
                $im->setResolution(300, 300);
                
                // 2. Force white background early
                $im->setBackgroundColor(new ImagickPixel('white'));
                
                $im->readImage($input_source);

                // 3. Force sRGB Colorspace (Crucial for fixing dark gradients/gold colors)
                $im->setImageColorspace(Imagick::COLORSPACE_SRGB);
                if (method_exists($im, 'transformImageColorspace')) {
                    $im->transformImageColorspace(Imagick::COLORSPACE_SRGB);
                }

                // 4. Flatten transparency against white
                $im->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                $im = $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

                // 5. High-quality scaling to 510px width (for 170px @3x)
                $im->resizeImage(510, 0, Imagick::FILTER_LANCZOS, 1);

                // 6. Output as high-quality JPG
                $im->setImageFormat('jpeg');
                $im->setImageCompressionQuality(95);
                $im->writeImage($dest_file);

                // Cleanup
                $im->clear();
                $im->destroy();

                return $dest_url;
            } catch (Exception $e) {
                error_log('[Politeia] SVG to JPG conversion failed: ' . $e->getMessage());
            }
        }

        // Fallback to original URL
        return $logo_url;
    }
}
