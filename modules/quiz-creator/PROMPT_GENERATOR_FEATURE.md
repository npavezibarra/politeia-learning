# ✅ ChatGPT Prompt Generator - Feature Added

## 🎯 **New Feature: Dynamic Prompt Generator**

The plugin now includes an **interactive ChatGPT Prompt Generator** that allows users to create customized prompts based on their specific needs.

---

## 🎨 **How It Works**

### **User Inputs**

1. **Topic** (Required)
   - The main subject for the quiz questions
   - Example: "Rome", "World War II", "Photosynthesis"

2. **Number of Questions** (Required)
   - How many questions to generate
   - Default: 10
   - Range: 1-100

3. **Keywords** (Optional)
   - Comma-separated list of specific topics to focus on
   - Example: "early republic, economy, demography"
   - Helps narrow down the scope of questions

---

## 📝 **Example Usage**

### **Input:**
```
Topic: Rome
Number of Questions: 15
Keywords: early republic, economy, demography
```

### **Generated Prompt:**
```
Create 15 quiz questions about "Rome" in JSON format with this structure:

[
  {
    "title": "Question title",
    "question_text": "Full question text",
    "answer_type": "single",
    "points": 5,
    "answers": [
      {"text": "Answer 1", "correct": true, "points": 0},
      {"text": "Answer 2", "correct": false, "points": 0},
      {"text": "Answer 3", "correct": false, "points": 0},
      {"text": "Answer 4", "correct": false, "points": 0}
    ]
  }
]

Focus the questions on these specific keywords:
- early republic
- economy
- demography

Requirements:
- Each question should have 4 answer options
- Only one correct answer per question (unless using "multiple" answer_type)
- Make questions clear and unambiguous
- Ensure answers are distinct and logical
- Return ONLY the JSON array, no additional text
```

---

## 🔧 **Technical Implementation**

### **Files Modified**

1. **templates/upload-form.php**
   - Added prompt generator input fields
   - Topic, Number, Keywords inputs
   - Generate button
   - Dynamic prompt display area

2. **assets/css/quiz-creator.css**
   - Styled prompt input fields
   - Added grid layout for inputs
   - Styled generate button
   - Added responsive styles for mobile

3. **assets/js/quiz-creator.js**
   - Added `initPromptGenerator()` function
   - Added `buildChatGPTPrompt()` function
   - Dynamic prompt generation
   - Keyword parsing and formatting
   - Smooth scroll to generated prompt

---

## 🎨 **UI Components**

### **Prompt Generator Section**
```
┌─────────────────────────────────────────┐
│   ChatGPT Prompt Generator               │
├─────────────────────────────────────────┤
│  Topic: [Rome_______________] *          │
│  Number: [10] *                          │
│  Keywords: [early republic, economy...] │
│  (Optional: Add specific keywords)       │
│                                          │
│        [ Generate Prompt ]               │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│  Copy this prompt and paste into ChatGPT │
│  ┌───────────────────────────────────┐  │
│  │ Create 10 quiz questions about... │  │
│  │ [Full generated prompt]           │  │
│  └───────────────────────────────────┘  │
│        [ Copy Prompt ]                   │
└─────────────────────────────────────────┘
```

---

## ✨ **Features**

### **1. Smart Keyword Handling**
- Parses comma-separated keywords
- Trims whitespace
- Formats as bullet list in prompt
- Skips empty keywords

### **2. Validation**
- Requires topic before generating
- Requires valid number (1-100)
- Shows alerts for missing required fields
- Auto-focuses on error field

### **3. Dynamic Display**
- Prompt box hidden until generated
- Smooth slide-down animation
- Auto-scroll to generated prompt
- Copy button for easy clipboard access

### **4. Responsive Design**
- 2-column grid on desktop
- Single column on mobile
- Touch-friendly buttons
- Optimized for all screen sizes

---

## 🚀 **User Workflow**

```
1. User fills in Topic: "Rome"
   ↓
2. User sets Number: 15
   ↓
3. User adds Keywords: "early republic, economy, demography"
   ↓
4. User clicks "Generate Prompt"
   ↓
5. Prompt appears with custom parameters
   ↓
6. User clicks "Copy Prompt"
   ↓
7. User pastes into ChatGPT
   ↓
8. ChatGPT generates JSON questions
   ↓
9. User downloads JSON file
   ↓
10. User uploads to quiz creator
    ↓
11. Quiz created! ✅
```

---

## 📋 **Prompt Template Structure**

### **Base Template**
```
Create [NUMBER] quiz questions about "[TOPIC]" in JSON format...
```

### **With Keywords**
```
Focus the questions on these specific keywords:
- [KEYWORD_1]
- [KEYWORD_2]
- [KEYWORD_3]
```

### **Requirements Section**
```
Requirements:
- Each question should have 4 answer options
- Only one correct answer per question
- Make questions clear and unambiguous
- Ensure answers are distinct and logical
- Return ONLY the JSON array, no additional text
```

---

## 💡 **Benefits**

✅ **No Manual Editing** - Users don't need to edit prompt templates  
✅ **Customizable** - Each quiz can have different parameters  
✅ **Focused Questions** - Keywords help narrow the scope  
✅ **Consistent Format** - Always generates proper JSON structure  
✅ **User-Friendly** - Simple form inputs, no technical knowledge needed  
✅ **Flexible** - Works for any topic or subject matter  

---

## 🎓 **Example Use Cases**

### **History Quiz**
```
Topic: Ancient Egypt
Number: 20
Keywords: pyramids, pharaohs, hieroglyphics, Nile River
```

### **Science Quiz**
```
Topic: Photosynthesis
Number: 12
Keywords: chlorophyll, light reactions, Calvin cycle, glucose
```

### **Literature Quiz**
```
Topic: Shakespeare
Number: 15
Keywords: Hamlet, Romeo and Juliet, sonnets, Globe Theatre
```

---

## 🔄 **Integration with Existing Features**

The prompt generator seamlessly integrates with:

✅ **Settings Dashboard** - Quiz settings configured separately  
✅ **File Upload** - Generated JSON uploaded as questions file  
✅ **Sample Files** - Still available for reference  
✅ **Format Viewer** - Shows JSON structure examples  
✅ **Copy to Clipboard** - Quick copy functionality  

---

## 📱 **Responsive Behavior**

### **Desktop (>768px)**
- 2-column grid for Topic and Number
- Full-width Keywords field
- Spacious layout

### **Mobile (<768px)**
- Single column layout
- Stacked inputs
- Touch-optimized buttons
- Compact spacing

---

## ✅ **Testing Checklist**

- [x] Topic validation works
- [x] Number validation works
- [x] Keywords parsing works
- [x] Prompt generation works
- [x] Copy to clipboard works
- [x] Smooth animations work
- [x] Responsive layout works
- [x] Integration with quiz creation works

---

**Feature Status:** ✅ Complete and Ready  
**Added:** February 13, 2026  
**Version:** 1.0.0
