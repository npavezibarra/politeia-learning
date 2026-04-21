# ✅ Politeia Quiz Creator - Updated Architecture

## 🔄 **Major Change: Settings Dashboard Separation**

The plugin has been refactored to separate **quiz settings** from **question data**:

### **Before (Old Design)**
- Single file upload containing both settings and questions
- Settings embedded in JSON/CSV/XML/TXT files

### **After (New Design)**
- **Settings Dashboard**: Form inputs for quiz configuration
- **Questions File**: Only contains questions and answers
- Settings and questions merged on submission

---

## 📋 **New Workflow**

```
User fills settings form (title, time limit, etc.)
   ↓
User uploads questions file (JSON/CSV/XML/TXT)
   ↓
User clicks "Create Quiz"
   ↓
JavaScript collects form data + file
   ↓
AJAX sends both to server
   ↓
Server merges settings + questions
   ↓
Quiz created in LearnDash
```

---

## 🎨 **New UI Structure**

### **Section 1: Quiz Settings Dashboard**
```
┌─────────────────────────────────────┐
│  Quiz Settings                       │
├─────────────────────────────────────┤
│  Quiz Title: [________________]      │
│  Time Limit: [___] minutes           │
│  Passing %:  [80_]                   │
│  ☑ Respect Question Order            │
│  ☐ Allow Only One Attempt            │
│  ☐ Force Answer Before Next          │
│  ☐ Show Points to Students           │
└─────────────────────────────────────┘
```

### **Section 2: Questions Upload**
```
┌─────────────────────────────────────┐
│  Upload Questions                    │
├─────────────────────────────────────┤
│  ┌───────────────────────────────┐  │
│  │   📤 Drag & Drop or Click     │  │
│  │   JSON, CSV, XML, TXT         │  │
│  └───────────────────────────────┘  │
└─────────────────────────────────────┘
```

### **Submit Button**
```
        [  Create Quiz  ]
```

---

## 📄 **New File Format (Questions Only)**

### **JSON Format (Recommended)**
```json
[
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
```

### **CSV Format**
```csv
Question 1,Question text,single,5,Answer 1,true,Answer 2,false
Question 2,Question text,single,5,Answer 1,false,Answer 2,true
```

### **XML Format**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<questions>
    <question>
        <title>Question title</title>
        <text>Question text</text>
        <answer_type>single</answer_type>
        <points>5</points>
        <answers>
            <answer>
                <text>Answer 1</text>
                <correct>true</correct>
            </answer>
        </answers>
    </question>
</questions>
```

### **TXT Format**
```
Q: Question text
A: Answer 1 (correct)
A: Answer 2

Q: Another question
A: Answer 1 (correct)
A: Answer 2
```

---

## 🤖 **Updated ChatGPT Prompt**

```
Create quiz questions in JSON format with this structure:

[
  {
    "title": "Question title",
    "question_text": "Full question text",
    "answer_type": "single",
    "points": 5,
    "answers": [
      {"text": "Answer 1", "correct": true, "points": 0},
      {"text": "Answer 2", "correct": false, "points": 0},
      {"text": "Answer 3", "correct": false, "points": 0}
    ]
  }
]

Create [NUMBER] questions about [YOUR TOPIC].
```

**Note:** No need to include quiz title or settings in the ChatGPT prompt anymore!

---

## ⚙️ **Settings Available in Dashboard**

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| **Quiz Title** | Text | Required | Name of the quiz |
| **Time Limit** | Number | 0 | Minutes (0 = no limit) |
| **Passing Percentage** | Number | 80 | Percentage to pass |
| **Respect Question Order** | Checkbox | On | Keep the file/manual order (off = random) |
| **Allow Only One Attempt** | Checkbox | Off | Limit to one attempt |
| **Force Answer Before Next** | Checkbox | Off | Must answer to proceed |
| **Show Points** | Checkbox | Off | Display points to students |

---

## 🔧 **Technical Changes**

### **Files Modified**

1. **templates/upload-form.php**
   - Added settings dashboard section
   - Restructured layout (settings + upload)
   - Added form inputs for all quiz settings

2. **assets/css/quiz-creator.css**
   - Added dashboard grid styling
   - Added form field styles
   - Added checkbox styling
   - Improved section layout

3. **assets/js/quiz-creator.js**
   - Added `collectSettings()` function
   - Modified `uploadQuiz()` to send settings
   - Updated form validation
   - Updated sample formats (questions only)

4. **includes/class-ajax-handler.php**
   - Modified `handle_upload()` to receive settings from POST
   - Merges settings with parsed questions
   - Creates combined quiz data structure

5. **includes/class-file-parser.php**
   - All parse methods now return questions array only
   - Removed settings parsing from files
   - Updated `normalize_questions()` method
   - Updated sample data (questions only)

6. **sample-quiz.json**
   - Updated to questions-only format
   - Removed settings section

---

## ✅ **Benefits of New Architecture**

### **1. Better User Experience**
- Clear separation of concerns
- Visual form inputs instead of JSON editing
- Easier to understand and use

### **2. Simpler ChatGPT Integration**
- ChatGPT only generates questions
- No need to explain settings structure
- Cleaner, shorter prompts

### **3. More Flexible**
- Users can reuse question files with different settings
- Easy to adjust settings without re-uploading
- Settings can be changed before each upload

### **4. Better Validation**
- Form validation for settings
- File validation for questions
- Clear error messages for each section

---

## 📝 **Usage Example**

### **Step 1: Fill Settings**
```
Quiz Title: Introduction to Political Science
Time Limit: 10 minutes
Passing %: 70
✓ Answers are always shuffled
```

### **Step 2: Get Questions from ChatGPT**
```
Prompt: Create 10 questions about political science

ChatGPT returns JSON array of questions
```

### **Step 3: Upload & Create**
```
Upload the JSON file
Click "Create Quiz"
Done!
```

---

## 🎯 **What Stays the Same**

✅ Multi-format support (JSON, CSV, XML, TXT)  
✅ Drag & drop upload  
✅ Sample file downloads  
✅ Format documentation  
✅ LearnDash integration  
✅ Security & validation  
✅ Error handling  

---

## 🚀 **Ready to Use!**

The plugin is fully updated and ready for testing:

1. ✅ Activate plugin
2. ✅ Add shortcode: `[politeia_quiz_creator]`
3. ✅ Fill in quiz settings
4. ✅ Upload questions file
5. ✅ Create quiz!

---

**Updated:** February 13, 2026  
**Version:** 1.0.0 (Refactored)  
**Status:** ✅ Complete
