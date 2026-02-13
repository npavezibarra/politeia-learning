# Politeia Quiz Creator

**Version:** 1.0.0  
**Requires:** WordPress 5.8+, LearnDash LMS  
**License:** GPL v2 or later

## Description

Politeia Quiz Creator is a WordPress plugin that allows you to create LearnDash quizzes by uploading structured files. Perfect for AI-assisted quiz creation with ChatGPT!

## Features

✅ **Multiple File Formats**: JSON, CSV, XML, TXT  
✅ **Drag & Drop Upload**: Modern, intuitive interface  
✅ **ChatGPT Integration**: Pre-built prompt templates  
✅ **Automatic Quiz Creation**: Converts files to complete LearnDash quizzes  
✅ **Sample Files**: Download examples for each format  
✅ **Validation**: Comprehensive error checking  
✅ **Flexible Settings**: Time limits, randomization, prerequisites, etc.

## Installation

1. Upload the `politeia-quiz-creator` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure LearnDash LMS is installed and activated
4. Use the shortcode `[politeia_quiz_creator]` on any page or post

## Usage

### Basic Shortcode

```
[politeia_quiz_creator]
```

### Shortcode Attributes

```
[politeia_quiz_creator title="Upload Your Quiz" show_samples="yes"]
```

**Attributes:**
- `title` - Custom heading (default: "Create Quiz from File")
- `show_samples` - Show sample files section (default: "yes")

### Using with ChatGPT

1. Copy the ChatGPT prompt template from the upload page
2. Paste it into ChatGPT with your quiz topic
3. ChatGPT will generate a properly formatted JSON file
4. Download the file and upload it to your site
5. Quiz is created automatically!

## File Formats

### JSON (Recommended)

Most flexible format, perfect for ChatGPT generation.

```json
{
  "title": "Quiz Title",
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
```

### CSV

Spreadsheet-friendly format.

```
Quiz Title
Question 1,Question text,single,5,Answer 1,true,Answer 2,false
Question 2,Question text,single,5,Answer 1,false,Answer 2,true
```

### XML

Structured markup format.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<quiz>
    <title>Quiz Title</title>
    <settings>
        <time_limit>300</time_limit>
    </settings>
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
</quiz>
```

### TXT

Simple text format.

```
QUIZ: Quiz Title
TIME_LIMIT: 300

Q: Question text
A: Answer 1 (correct)
A: Answer 2

Q: Another question
A: Answer 1 (correct)
A: Answer 2
```

## Quiz Settings

### Available Settings

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `time_limit` | int | 0 | Time limit in seconds (0 = no limit) |
| `random_questions` | int | 0 | Randomize question order (0 = no, 1 = yes) |
| `random_answers` | int | 0 | Randomize answer order (0 = no, 1 = yes) |
| `run_once` | int | 0 | Limit quiz attempts (0 = no limit, 1 = yes) |
| `run_once_type` | int | 0 | 0=user, 1=cookie, 2=IP, 3=both |
| `force_solve` | int | 0 | Force answering before next (0 = no, 1 = yes) |
| `show_points` | int | 0 | Show points to user (0 = no, 1 = yes) |
| `passing_percentage` | int | 80 | Passing percentage |

### Answer Types

- `single` - Single choice (radio buttons)
- `multiple` - Multiple choice (checkboxes)
- `free_answer` - Text input
- `sort_answer` - Sorting questions
- `cloze_answer` - Fill in the blank

## ChatGPT Prompt Template

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

## Examples

### Example 1: Political Science Quiz

Ask ChatGPT:
```
Create a quiz about Political Science with 10 questions using the JSON format above.
```

### Example 2: History Quiz with Settings

Ask ChatGPT:
```
Create a quiz about World War II with 15 questions. 
Set a 20-minute time limit and randomize the answer order.
Use the JSON format above.
```

### Example 3: Multiple Choice Quiz

Ask ChatGPT:
```
Create a quiz about Biology with 8 multiple-choice questions.
Each question should have 4 possible answers.
Use the JSON format above.
```

## Permissions

By default, any user with `edit_posts` capability can upload quizzes. This includes:
- Administrators
- Editors
- Authors

To customize permissions, use the WordPress capability system.

## Troubleshooting

### Quiz Not Creating

1. Check that LearnDash is installed and activated
2. Verify file format is correct (use sample files as reference)
3. Check that all questions have at least one correct answer
4. Ensure file size is under 5MB

### File Upload Errors

1. Check PHP upload limits in `php.ini`
2. Verify file permissions on uploads directory
3. Check for JavaScript errors in browser console

### Questions Not Appearing

1. Verify question structure in uploaded file
2. Check that `answers` array is properly formatted
3. Ensure at least one answer is marked as correct

## Support

For support, please contact: support@politeia.com

## Changelog

### 1.0.0
- Initial release
- JSON, CSV, XML, TXT support
- ChatGPT integration
- Drag & drop upload
- Sample file downloads

## Credits

Developed by Politeia Team  
Based on LearnDash LMS architecture

## License

GPL v2 or later
