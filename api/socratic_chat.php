<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'student') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$config_path = __DIR__ . '/../includes/config.php';
if (!file_exists($config_path)) {
    echo json_encode(['error' => 'Config file not found']);
    exit;
}

include $config_path;

if (!isset($conn) || !$conn) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$student_id  = $_SESSION['user_id'];
$message     = trim($_POST['message'] ?? '');
$session_id  = isset($_POST['session_id'])  ? intval($_POST['session_id'])  : null;
$hint_level  = isset($_POST['hint_level'])  ? intval($_POST['hint_level'])  : 1;

// ==========================================
// DETECT MESSAGE TYPE & INTENT
// ==========================================

function detectMessageType($message) {
    $lower = strtolower($message);
    $word_count = str_word_count($message);
    $sentence_count = preg_match_all('/[.!?]+/', $message);
    
    // 1. Check if asking for direct answer
    $direct_phrases = [
        'write me', 'give me', 'tell me the answer', 'what is the answer',
        'solve this', 'do this for me', 'write code for', 'give me code',
        'write an essay', 'write essay', 'can you write', 'please write',
        'answer this', 'help me solve', 'i need the answer'
    ];
    foreach ($direct_phrases as $phrase) {
        if (strpos($lower, $phrase) !== false) {
            return 'direct_request';
        }
    }
    
    // 2. Check if student says they are ready to write/submit
    $ready_phrases = [
        "i'll write", "i will write", "i have enough ideas", 
        "i can write now", "let me write", "ready to write",
        "i'll start writing", "going to write", "i'm done thinking",
        "i have everything i need", "can you review", "please review",
        "review my essay", "check my work", "i'll submit", "i will submit",
        "i'm ready to write", "i am ready to write", "enough ideas",
        "i think i have enough", "i have enough", "ready to start writing",
        "i'll write the essay", "going to write now", "starting to write"
    ];
    foreach ($ready_phrases as $phrase) {
        if (strpos($lower, $phrase) !== false) {
            return 'ready_to_write';
        }
    }
    
    // 3. Check if student says "I don't know"
    $unknown_phrases = [
        "i don't know", "i dont know", "not sure", "unsure",
        "i have no idea", "no idea", "i'm stuck", "im stuck",
        "i don't understand", "i dont understand", "confused",
        "i dunno", "dunno", "no clue"
    ];
    foreach ($unknown_phrases as $phrase) {
        if (strpos($lower, $phrase) !== false) {
            return 'unknown';
        }
    }
    
    // 4. Check if it's an essay (long text with multiple sentences)
    if ($word_count > 40 && $sentence_count > 3) {
        return 'essay_evaluation';
    }
    
    // 5. Check if it's a coding error question
    $code_keywords = ['error', 'bug', 'syntax', 'undefined', 'null', 'exception', 
                      'fail', 'not working', "doesn't work", 'does not work',
                      'parse error', 'runtime error', 'fatal error', 'warning'];
    foreach ($code_keywords as $keyword) {
        if (strpos($lower, $keyword) !== false) {
            return 'code_error';
        }
    }
    
    // 6. Check if it's a code snippet or code question
    if (preg_match('/[{};=()]|function|class|def|if|else|for|while|return|print|echo/', $lower)) {
        return 'code_question';
    }
    
    // Default: general question
    return 'general_question';
}

$message_type = detectMessageType($message);

// ==========================================
// GET OR CREATE CHAT SESSION
// ==========================================

if ($session_id) {
    $check_sql  = "SELECT session_id FROM chat_session WHERE session_id = ? AND student_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "ii", $session_id, $student_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($check_result) == 0) {
        $session_id = null;
    }
}

if (!$session_id) {
    $session_title = substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '');
    $insert_sql    = "INSERT INTO chat_session (student_id, session_title) VALUES (?, ?)";
    $insert_stmt   = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($insert_stmt, "is", $student_id, $session_title);
    mysqli_stmt_execute($insert_stmt);
    $session_id = mysqli_insert_id($conn);
}

// ==========================================
// SAVE STUDENT MESSAGE
// ==========================================

$save_sql  = "INSERT INTO chat_message (session_id, student_id, sender, message) VALUES (?, ?, 'student', ?)";
$save_stmt = mysqli_prepare($conn, $save_sql);
mysqli_stmt_bind_param($save_stmt, "iis", $session_id, $student_id, $message);
mysqli_stmt_execute($save_stmt);

// ==========================================
// SYSTEM PROMPT - ADAPTIVE BASED ON INTENT
// ==========================================

function getSystemPrompt($message_type, $hint_level) {
    // Default prompt for hint-giving mode
    $base_prompt = "You are a helpful AI tutor. Your goal is to HELP students learn by giving hints and ideas.
    
RULES:
1. DO give hints, ideas, analogies, and partial explanations.
2. DO NOT give complete answers or write full solutions.
3. DO give examples that are SIMILAR but not the exact answer.
4. For essays: give feedback on structure, suggest improvements, point out strengths/weaknesses.
5. For code: explain concepts, point out common mistakes, give debugging strategies.
6. For 'I don't know': give a starting point or example to build from.
7. End each response with a question that asks the student to try something.
8. Be encouraging and supportive.
9. Keep responses clear and concise.
10. Help the student build confidence to solve problems independently.";

    // ==========================================
    // READY TO WRITE - SWITCH TO FEEDBACK MODE
    // ==========================================
    if ($message_type === 'ready_to_write') {
        return "You are a helpful AI tutor. The student has indicated they are ready to write/submit their work.

CRITICAL INSTRUCTION: The student has finished their thinking phase. DO NOT ask more guiding questions. Instead:

1. Acknowledge that they have developed good ideas.
2. Encourage them to start writing their draft.
3. Tell them to submit their work when ready for review.
4. Offer to review, edit, and provide feedback on their work.
5. Remind them of the key points they should include.

Be encouraging and supportive. Tell them you're looking forward to reviewing their work.

Example response: 
'💡 That's great! You've developed some really thoughtful ideas. When you finish your draft, paste it here and I'll help you refine it. I'll provide feedback on structure, clarity, and arguments. Take your time writing!'";
    }

    // ==========================================
    // ESSAY EVALUATION - GIVE FEEDBACK
    // ==========================================
    if ($message_type === 'essay_evaluation') {
        return "You are a helpful AI tutor. The student has submitted an essay for evaluation.

Provide constructive feedback:
1. Point out what they did well.
2. Suggest improvements to structure, argument, or evidence.
3. Ask clarifying questions about their main points.
4. Give an example of how to strengthen a weak section.
5. End with: 'Here are some suggestions to improve your essay. Try revising these sections.'

Be specific and helpful. Don't rewrite the essay for them."; 
    }

    // ==========================================
    // CODE ERROR - DEBUGGING HELP
    // ==========================================
    if ($message_type === 'code_error') {
        return "You are a helpful AI tutor. The student is getting a coding error.

Help them debug:
1. Explain what the error message means in simple terms.
2. Suggest common causes of this error.
3. Give a small code example showing the correct pattern.
4. Point out where to check in their code.
5. End with: 'Check your code at the line mentioned. Does it match the pattern shown?'";
    }

    // ==========================================
    // CODE QUESTION - CONCEPT EXPLANATION
    // ==========================================
    if ($message_type === 'code_question') {
        return "You are a helpful AI tutor. The student is asking about code.

Provide a helpful explanation:
1. Explain the concept with a simple example.
2. Show a small working code snippet that demonstrates the idea.
3. Point out important syntax or logic to remember.
4. End with: 'Now try writing your own version based on this example.'";
    }

    // ==========================================
    // DIRECT REQUEST - GIVE HINTS
    // ==========================================
    if ($message_type === 'direct_request') {
        return $base_prompt . "

The student asked for a direct answer.
Instead of giving the full answer:
- Give a hint or analogy that points them in the right direction.
- Provide a partial example or starting point.
- Explain the core concept briefly.
- End with: 'Now try applying this to your problem.'";
    }

    // ==========================================
    // UNKNOWN - GIVE STARTING POINT
    // ==========================================
    if ($message_type === 'unknown') {
        return $base_prompt . "

The student says they don't know.
Give them a starting point:
- Explain the basic concept in simple terms.
- Give a simple example they can build from.
- Suggest a small first step they can try.
- End with: 'Try this approach and see what happens.'";
    }

    // ==========================================
    // GENERAL QUESTION
    // ==========================================
    // Hint level: 1 = gentle hint, 2 = more detailed hint, 3 = worked example
    $level_instructions = [
        1 => "Give a brief, gentle hint (2-3 sentences). Point the student in the right direction without giving too much away.",
        2 => "Give a more detailed hint (3-4 sentences). Include an analogy or example to help the student understand.",
        3 => "Give a complete worked example for a SIMILAR but different problem. Show step-by-step how to solve it, then ask the student to apply the same approach."
    ];
    
    $level = $level_instructions[$hint_level] ?? $level_instructions[1];
    
    return $base_prompt . "\n\n" . $level;
}

$system_prompt = getSystemPrompt($message_type, $hint_level);
$ai_reply = '';
$provider_used = '';

// ==========================================
// API KEYS & MODELS
// ==========================================

$groq_api_key    = "";
$mistral_api_key = "";

$groq_models    = ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant', 'mixtral-8x7b-32768'];
$mistral_models = ['mistral-small-latest', 'mistral-tiny-latest'];

// ==========================================
// API CALL FUNCTIONS
// ==========================================

function callGroq($api_key, $model, $system_prompt, $message) {
    if (empty($api_key)) return null;

    $url     = "https://api.groq.com/openai/v1/chat/completions";
    $payload = [
        'model'    => $model,
        'messages' => [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user',   'content' => $message]
        ],
        'temperature' => 0.7,
        'max_tokens'  => 600
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT,        30);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) {
            return trim($data['choices'][0]['message']['content']);
        }
    }
    return null;
}

function callMistral($api_key, $model, $system_prompt, $message) {
    if (empty($api_key)) return null;

    $url     = "https://api.mistral.ai/v1/chat/completions";
    $payload = [
        'model'    => $model,
        'messages' => [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user',   'content' => $message]
        ],
        'temperature' => 0.7,
        'max_tokens'  => 600
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT,        30);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) {
            return trim($data['choices'][0]['message']['content']);
        }
    }
    return null;
}

// ==========================================
// GENERATE RESPONSE
// ==========================================

$system_prompt = getSystemPrompt($message_type, $hint_level);

// For 'ready_to_write', we can use a simpler prompt without API call if needed
if ($message_type === 'ready_to_write') {
    // Try API first, but fallback to template if needed
    $ai_reply = callGroq($groq_api_key, $groq_models[0], $system_prompt, $message);
    if (empty($ai_reply)) {
        $ai_reply = callMistral($mistral_api_key, $mistral_models[0], $system_prompt, $message);
    }
}

// Try Groq
if (empty($ai_reply)) {
    foreach ($groq_models as $model) {
        $result = callGroq($groq_api_key, $model, $system_prompt, $message);
        if ($result) {
            $ai_reply = $result;
            $provider_used = 'Groq (' . $model . ')';
            break;
        }
    }
}

// Fallback to Mistral
if (empty($ai_reply)) {
    foreach ($mistral_models as $model) {
        $result = callMistral($mistral_api_key, $model, $system_prompt, $message);
        if ($result) {
            $ai_reply = $result;
            $provider_used = 'Mistral (' . $model . ')';
            break;
        }
    }
}

// ==========================================
// FALLBACK RESPONSES
// ==========================================

if (empty($ai_reply)) {
    $fallbacks = [
        'ready_to_write' => [
            "💡 That's great! You've developed some really thoughtful ideas on this topic.

When you finish your draft, paste it here and I'll help you:
- ✅ Refine your arguments
- ✅ Improve clarity and flow
- ✅ Check structure and organization
- ✅ Suggest where you can add more detail or evidence

Take your time writing - I'll be here to help you polish your work! 🎯"
        ],
        'direct_request' => [
            "💡 **Hint:** Try breaking down the problem into smaller parts. Start with what you already know, then think about what you need to find. Write down the steps you would take to solve it.\n\n✏️ **Try this:** Write out the problem in your own words, then list the steps to solve it."
        ],
        'unknown' => [
            "💡 **Let's get started:** A good place to begin is by understanding what the question is asking. Can you rephrase it in your own words? Start with a simple version of the problem and build from there.\n\n✏️ **Try this:** Write down one thing you know about this topic."
        ],
        'essay_evaluation' => [
            "📝 **Feedback on your essay:**\n\n✅ **Strengths:** You have a clear topic and some good points.\n💡 **Improvements:** Consider adding more evidence to support your arguments. Try using specific examples.\n📊 **Structure:** Make sure each paragraph has one main idea.\n\n✏️ **Try this:** Rewrite your introduction to clearly state your main argument."
        ],
        'code_error' => [
            "🐛 **Debugging help:**\n\nThis error usually means that the variable hasn't been defined before you try to use it. Common causes:\n- Misspelling the variable name\n- Using a variable outside its scope\n- Forgetting to declare it\n\n✅ **Check:** Look at the line number in the error message. Is the variable declared before that line?\n\n✏️ **Try this:** Add `print` statements to see what values your variables have."
        ],
        'code_question' => [
            "💻 **Concept explanation:**\n\nA function is a reusable block of code. You define it once and call it many times.\n\n**Example:**
```python
def greet(name):
    return \"Hello, \" + name

print(greet(\"Alice\"))  # Hello, Alice
```\n\n✏️ **Try this:** Write a function that takes two numbers and returns their sum."
        ],
        'general_question' => [
            "💡 **Here's a helpful way to think about it:**\n\nStart by understanding the basic concept. Think of a real-world analogy that helps explain it. Then, try applying it to a simple example.\n\n✏️ **Try this:** Write down what you think this concept means in your own words."
        ]
    ];
    
    $pool = $fallbacks[$message_type] ?? $fallbacks['general_question'];
    $ai_reply = $pool[array_rand($pool)];
}

// ==========================================
// SAVE AI RESPONSE
// ==========================================

$sender_label = ($hint_level === 1) ? 'ai' : 'ai_hint_' . $hint_level;

$save_ai_sql  = "INSERT INTO chat_message (session_id, student_id, sender, message) VALUES (?, ?, ?, ?)";
$save_ai_stmt = mysqli_prepare($conn, $save_ai_sql);
mysqli_stmt_bind_param($save_ai_stmt, "iiss", $session_id, $student_id, $sender_label, $ai_reply);
mysqli_stmt_execute($save_ai_stmt);

$update_sql  = "UPDATE chat_session SET updated_at = NOW() WHERE session_id = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "i", $session_id);
mysqli_stmt_execute($update_stmt);

echo json_encode([
    'reply'        => $ai_reply,
    'session_id'   => $session_id,
    'hint_level'   => $hint_level,
    'message_type' => $message_type,
    'provider'     => $provider_used
]);

mysqli_close($conn);
?>