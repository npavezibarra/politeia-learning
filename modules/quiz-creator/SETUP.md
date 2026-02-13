# Politeia Quiz Creator - Quick Setup Guide

## ✅ Plugin Successfully Created!

The plugin has been built and is ready to use. Follow these steps to get started:

---

## 📦 Installation Steps

### 1. Activate the Plugin

1. Go to **WordPress Admin** → **Plugins**
2. Find **"Politeia Quiz Creator"**
3. Click **"Activate"**

### 2. Create a Test Page

1. Go to **Pages** → **Add New**
2. Title: "Quiz Creator"
3. Add the shortcode: `[politeia_quiz_creator]`
4. Click **Publish**

### 3. Test the Upload

1. Visit your new page
2. You'll see the upload interface with:
   - Drag & drop area
   - Sample file downloads
   - ChatGPT prompt template
   - Format documentation

---

## 🧪 Testing the Plugin

### Option 1: Use the Sample File

A sample quiz file is included at:
```
wp-content/plugins/politeia-quiz-creator/sample-quiz.json
```

1. Download this file
2. Upload it via the shortcode interface
3. A quiz called "Introduction to Political Science" will be created

### Option 2: Use ChatGPT

1. Copy the ChatGPT prompt from the upload page
2. Paste into ChatGPT with your topic
3. Save the JSON output
4. Upload to your site

---

## 📁 Plugin Structure

```
politeia-quiz-creator/
├── politeia-quiz-creator.php    # Main plugin file
├── README.md                     # Full documentation
├── CHATGPT_INSTRUCTIONS.md      # ChatGPT guide
├── sample-quiz.json             # Test file
├── includes/
│   ├── class-quiz-creator.php   # Quiz creation logic
│   ├── class-file-parser.php    # File parsing (JSON/CSV/XML/TXT)
│   ├── class-shortcode.php      # Shortcode handler
│   └── class-ajax-handler.php   # AJAX upload handler
├── templates/
│   └── upload-form.php          # Upload interface
├── assets/
│   ├── css/
│   │   └── quiz-creator.css     # Styles
│   └── js/
│       └── quiz-creator.js      # JavaScript
```

---

## 🎯 Supported File Formats

### 1. JSON (Recommended)
- Most flexible
- Perfect for ChatGPT
- Full settings support

### 2. CSV
- Spreadsheet-friendly
- Easy to edit in Excel
- Basic settings

### 3. XML
- Structured markup
- Full settings support

### 4. TXT
- Simple text format
- Human-readable
- Basic settings

---

## ⚙️ Features

✅ **Drag & Drop Upload** - Modern interface  
✅ **Multiple Formats** - JSON, CSV, XML, TXT  
✅ **ChatGPT Integration** - Pre-built prompts  
✅ **Sample Downloads** - Examples for each format  
✅ **Format Viewer** - See structure documentation  
✅ **Validation** - Comprehensive error checking  
✅ **Auto-Creation** - Complete LearnDash quizzes  
✅ **Flexible Settings** - Time limits, randomization, etc.

---

## 🚀 Usage Workflow

```
1. User asks ChatGPT to create a quiz
   ↓
2. ChatGPT generates JSON file
   ↓
3. User downloads the file
   ↓
4. User uploads to WordPress via shortcode
   ↓
5. Plugin creates complete LearnDash quiz
   ↓
6. Quiz is ready to use!
```

---

## 📝 Quiz Settings Available

| Setting | Description | Default |
|---------|-------------|---------|
| `time_limit` | Time in seconds (0 = no limit) | 0 |
| `random_questions` | Randomize question order | 0 |
| `random_answers` | Randomize answer order | 0 |
| `run_once` | Limit quiz attempts | 0 |
| `run_once_type` | 0=user, 1=cookie, 2=IP | 0 |
| `force_solve` | Must answer before next | 0 |
| `show_points` | Show points to user | 0 |
| `passing_percentage` | Passing grade | 80 |

---

## 🔍 Troubleshooting

### Plugin Not Showing

**Issue**: Shortcode displays as text  
**Solution**: Make sure plugin is activated

### LearnDash Required

**Issue**: Error message about LearnDash  
**Solution**: Install and activate LearnDash LMS

### Upload Fails

**Issue**: File won't upload  
**Solution**: 
- Check file format (JSON, CSV, XML, TXT)
- Verify file size < 5MB
- Ensure at least one correct answer per question

### Quiz Not Creating

**Issue**: Upload succeeds but no quiz appears  
**Solution**:
- Check WordPress error logs
- Verify JSON format is valid
- Ensure all required fields are present

---

## 📖 Documentation Files

1. **README.md** - Complete plugin documentation
2. **CHATGPT_INSTRUCTIONS.md** - Detailed ChatGPT guide with examples
3. **This file** - Quick setup guide

---

## 🎓 Example ChatGPT Prompt

```
Create a quiz in JSON format with the following structure:

{
  "title": "Quiz Title Here",
  "settings": {
    "time_limit": 300,
    "random_questions": 0,
    "random_answers": 0,
    "run_once": 0,
    "force_solve": 0,
    "passing_percentage": 80
  },
  "questions": [
    {
      "title": "Question title",
      "question_text": "Full question text",
      "answer_type": "single",
      "points": 5,
      "answers": [
        {"text": "Answer 1", "correct": true, "points": 0},
        {"text": "Answer 2", "correct": false, "points": 0}
      ]
    }
  ]
}

Create a quiz about [YOUR TOPIC] with [NUMBER] questions.
```

---

## ✨ Next Steps

1. **Activate the plugin** in WordPress
2. **Create a test page** with the shortcode
3. **Upload the sample quiz** to test functionality
4. **Try ChatGPT** to create your first AI-generated quiz
5. **Share with your team** and start creating quizzes!

---

**Plugin Version:** 1.0.0  
**Requires:** WordPress 5.8+, LearnDash LMS  
**Author:** Politeia Team

**Happy Quiz Creating! 🎉**
