# ChatGPT Instructions for Quiz Creation

## How to Use ChatGPT to Create Quizzes

This document provides instructions for using ChatGPT to generate quiz files compatible with the Politeia Quiz Creator plugin.

---

## Quick Start Prompt

Copy and paste this prompt into ChatGPT:

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
        {"text": "Answer 2", "correct": false, "points": 0},
        {"text": "Answer 3", "correct": false, "points": 0}
      ]
    }
  ]
}

Create a quiz about [YOUR TOPIC] with [NUMBER] questions.
```

Replace `[YOUR TOPIC]` and `[NUMBER]` with your specific requirements.

---

## Detailed Instructions

### Step 1: Define Your Quiz Requirements

Before asking ChatGPT, decide on:

1. **Topic**: What subject is the quiz about?
2. **Number of questions**: How many questions do you need?
3. **Difficulty level**: Easy, medium, hard?
4. **Question type**: Single choice, multiple choice, etc.
5. **Time limit**: How long should students have?
6. **Settings**: Any special requirements?

### Step 2: Use the Appropriate Prompt

#### Basic Quiz (Recommended)

```
Create a quiz about [TOPIC] with [NUMBER] questions in JSON format.

Use this structure:
{
  "title": "Quiz Title",
  "settings": {
    "time_limit": 0,
    "passing_percentage": 80
  },
  "questions": [
    {
      "title": "Question title",
      "question_text": "Full question text",
      "answer_type": "single",
      "points": 5,
      "answers": [
        {"text": "Answer", "correct": true, "points": 0},
        {"text": "Answer", "correct": false, "points": 0}
      ]
    }
  ]
}
```

#### Advanced Quiz with Custom Settings

```
Create a quiz about [TOPIC] with [NUMBER] questions in JSON format.

Requirements:
- Time limit: [X] minutes
- Randomize answer order: [yes/no]
- Passing percentage: [X]%
- Each question should have [X] answer options

Use this structure:
{
  "title": "Quiz Title",
  "settings": {
    "time_limit": [SECONDS],
    "random_answers": [0 or 1],
    "passing_percentage": [NUMBER]
  },
  "questions": [
    {
      "title": "Question title",
      "question_text": "Full question text",
      "answer_type": "single",
      "points": 5,
      "answers": [
        {"text": "Answer", "correct": true, "points": 0}
      ]
    }
  ]
}
```

### Step 3: Review ChatGPT's Output

ChatGPT will generate a JSON file. Check that:

- ✅ All questions have at least one correct answer
- ✅ The JSON is properly formatted (no syntax errors)
- ✅ Question text is clear and unambiguous
- ✅ Answer options are distinct and logical

### Step 4: Copy and Save

1. Copy the entire JSON output from ChatGPT
2. Open a text editor (Notepad, TextEdit, VS Code, etc.)
3. Paste the content
4. Save as `quiz.json` (or any name with `.json` extension)

### Step 5: Upload to WordPress

1. Go to your WordPress page with the `[politeia_quiz_creator]` shortcode
2. Drag and drop or click to upload your `quiz.json` file
3. Click "Create Quiz"
4. Done! Your quiz is now live in LearnDash

---

## Example Prompts

### Example 1: Simple Quiz

```
Create a quiz about "Introduction to Philosophy" with 10 questions in JSON format.

Each question should have 4 answer options with only one correct answer.

Use this structure:
{
  "title": "Quiz Title",
  "questions": [
    {
      "title": "Question title",
      "question_text": "Full question",
      "answer_type": "single",
      "points": 5,
      "answers": [
        {"text": "Answer", "correct": true, "points": 0}
      ]
    }
  ]
}
```

### Example 2: Timed Quiz

```
Create a quiz about "World History" with 15 questions in JSON format.

Requirements:
- 20-minute time limit
- Randomize answer order
- 70% passing grade
- 4 answer options per question

Use this structure:
{
  "title": "Quiz Title",
  "settings": {
    "time_limit": 1200,
    "random_answers": 1,
    "passing_percentage": 70
  },
  "questions": [
    {
      "title": "Question title",
      "question_text": "Full question",
      "answer_type": "single",
      "points": 5,
      "answers": [
        {"text": "Answer", "correct": true, "points": 0}
      ]
    }
  ]
}
```

### Example 3: Multiple Choice Quiz

```
Create a quiz about "Biology Basics" with 12 questions in JSON format.

Requirements:
- Multiple choice (students can select multiple correct answers)
- No time limit
- 80% passing grade
- Each question should have 5 options with 2-3 correct answers

Use this structure:
{
  "title": "Quiz Title",
  "settings": {
    "time_limit": 0,
    "passing_percentage": 80
  },
  "questions": [
    {
      "title": "Question title",
      "question_text": "Full question",
      "answer_type": "multiple",
      "points": 10,
      "answers": [
        {"text": "Answer", "correct": true, "points": 0}
      ]
    }
  ]
}
```

### Example 4: Difficult Quiz with Restrictions

```
Create a challenging quiz about "Advanced Mathematics" with 20 questions in JSON format.

Requirements:
- 30-minute time limit
- Can only be taken once (IP-based restriction)
- Must answer all questions before submitting
- Randomize both questions and answers
- 85% passing grade
- 3-4 answer options per question

Use this structure:
{
  "title": "Quiz Title",
  "settings": {
    "time_limit": 1800,
    "random_questions": 1,
    "random_answers": 1,
    "run_once": 1,
    "run_once_type": 2,
    "force_solve": 1,
    "passing_percentage": 85
  },
  "questions": [
    {
      "title": "Question title",
      "question_text": "Full question",
      "answer_type": "single",
      "points": 5,
      "answers": [
        {"text": "Answer", "correct": true, "points": 0}
      ]
    }
  ]
}
```

---

## Tips for Better Results

### 1. Be Specific

❌ Bad: "Create a quiz about history"  
✅ Good: "Create a quiz about the American Revolution with 15 questions, focusing on key battles and political figures"

### 2. Specify Difficulty

❌ Bad: "Create a quiz"  
✅ Good: "Create an intermediate-level quiz suitable for college freshmen"

### 3. Request Variety

❌ Bad: "Create questions"  
✅ Good: "Create questions with a mix of factual recall, analysis, and application"

### 4. Provide Context

❌ Bad: "Quiz about science"  
✅ Good: "Create a quiz for a high school biology class covering cell structure and function"

### 5. Ask for Refinements

If ChatGPT's first attempt isn't perfect, ask for changes:

```
"Make question 3 more challenging"
"Add more answer options to question 7"
"Change the time limit to 15 minutes"
"Make all questions worth 10 points instead of 5"
```

---

## Common Issues and Solutions

### Issue: JSON Syntax Error

**Problem**: ChatGPT sometimes adds extra text before or after the JSON.

**Solution**: Copy only the JSON part (from `{` to `}`), excluding any explanatory text.

### Issue: No Correct Answers

**Problem**: ChatGPT forgot to mark any answer as correct.

**Solution**: Ask ChatGPT to "ensure each question has at least one correct answer marked with 'correct': true"

### Issue: Inconsistent Format

**Problem**: ChatGPT changed the structure partway through.

**Solution**: Provide the complete structure in your prompt and ask ChatGPT to "strictly follow this exact format"

### Issue: Too Easy/Hard

**Problem**: Questions aren't at the right difficulty level.

**Solution**: Specify difficulty in your prompt: "Create beginner-level questions" or "Create advanced questions for experts"

---

## Settings Reference

### Time Limit

- `0` = No time limit
- `300` = 5 minutes
- `600` = 10 minutes
- `1200` = 20 minutes
- `1800` = 30 minutes

### Random Settings

- `0` = No randomization
- `1` = Randomize

### Run Once Type

- `0` = Only for logged-in users
- `1` = Cookie-based
- `2` = IP-based (recommended)
- `3` = Cookie + IP

### Answer Types

- `single` = Single choice (radio buttons)
- `multiple` = Multiple choice (checkboxes)
- `free_answer` = Text input
- `sort_answer` = Sorting
- `cloze_answer` = Fill in the blank

---

## Advanced: Batch Quiz Creation

To create multiple quizzes at once, ask ChatGPT:

```
Create 3 separate quizzes in JSON format:

1. "Introduction to Economics" - 10 questions, beginner level
2. "Microeconomics Basics" - 12 questions, intermediate level
3. "Macroeconomics Advanced" - 15 questions, advanced level

For each quiz, use this structure:
{
  "title": "Quiz Title",
  "questions": [...]
}

Provide each quiz as a separate JSON object.
```

Then save each quiz as a separate file and upload them individually.

---

## Need Help?

If you're having trouble getting ChatGPT to generate the right format:

1. Try the "Quick Start Prompt" at the top of this document
2. Download a sample JSON file from the upload page
3. Show the sample to ChatGPT and ask it to create a similar quiz
4. Contact support if issues persist

---

**Happy Quiz Creating! 🎓**
