(function() {
    var parts = window.PRS_MY_BOOK_PARTS;
    if (!parts || !parts.length) return;
    try {
        var blob = new Blob([parts.join("\n")], { type: 'application/javascript' });
        var url = URL.createObjectURL(blob);
        var script = document.createElement("script");
        script.src = url;
        script.async = false;
        document.head.appendChild(script);
    } catch (e) {
        // Fallback for very old browsers
        var script = document.createElement("script");
        script.text = parts.join("\n");
        document.head.appendChild(script);
    }
})();
