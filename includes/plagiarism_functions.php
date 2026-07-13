<?php
/**
 * Advanced Plagiarism Detection Functions
 * Uses shingling (n-gram) technique similar to Turnitin
 */

/**
 * Convert text into n-gram shingles
 * @param string $text Input text
 * @param int $n Size of each shingle (3-5 recommended)
 * @return array Array of unique shingles
 */
function getShingles($text, $n = 3) {
    // Clean text: lowercase, remove extra spaces, remove punctuation
    $clean = strtolower($text);
    $clean = preg_replace('/[^\w\s]/', ' ', $clean);  // Remove punctuation
    $clean = preg_replace('/\s+/', ' ', $clean);      // Normalize spaces
    $words = explode(' ', trim($clean));
    
    $shingles = [];
    $count = count($words);
    
    if ($count < $n) {
        return [];
    }
    
    for ($i = 0; $i <= $count - $n; $i++) {
        $shingle = implode(' ', array_slice($words, $i, $n));
        $shingles[$shingle] = true;  // Use as key for uniqueness
    }
    
    return array_keys($shingles);
}

/**
 * Calculate Jaccard similarity between two sets of shingles
 * Jaccard = |A ∩ B| / |A ∪ B|
 * @param array $shingles1 First shingle set
 * @param array $shingles2 Second shingle set
 * @return float Similarity percentage (0-100)
 */
function jaccardSimilarity($shingles1, $shingles2) {
    if (empty($shingles1) || empty($shingles2)) return 0;
    
    $intersection = count(array_intersect($shingles1, $shingles2));
    $union = count(array_unique(array_merge($shingles1, $shingles2)));
    
    if ($union == 0) return 0;
    
    return ($intersection / $union) * 100;
}

/**
 * Calculate similarity for ESSAY submissions (word-based, semantic)
 * @param string $text1 First essay
 * @param string $text2 Second essay  
 * @param string $lecturer_text Template to ignore
 * @return float Similarity percentage
 */
function calculateEssaySimilarity($text1, $text2, $lecturer_text = '') {
    
    
    // Use 4-word shingles for essays (better phrase detection)
    $shingles1 = getShingles($text1, 4);
    $shingles2 = getShingles($text2, 4);
    
    $jaccard = jaccardSimilarity($shingles1, $shingles2);
    
    // Also consider word frequency for better accuracy
    $words1 = getWordFrequency($text1);
    $words2 = getWordFrequency($text2);
    $wordSimilarity = wordFrequencySimilarity($words1, $words2);
    
    // Weighted combination: 70% shingle similarity, 30% word frequency
    return round(($jaccard * 0.7) + ($wordSimilarity * 0.3), 2);
}

/**
 * Calculate similarity for CODE submissions (structure + logic)
 * @param string $code1 First code submission
 * @param string $code2 Second code submission
 * @param string $lecturer_text Template to ignore
 * @return float Similarity percentage
 */
function calculateCodeSimilarity($code1, $code2, $lecturer_text = '') {
   
    // Remove comments for structural comparison
    $code1 = removeComments($code1);
    $code2 = removeComments($code2);
    
    // Normalize code (remove variable names for structure comparison)
    $normalized1 = normalizeCode($code1);
    $normalized2 = normalizeCode($code2);
    
    // Use 3-word shingles for code (smaller chunks better for code)
    $shingles1 = getShingles($normalized1, 3);
    $shingles2 = getShingles($normalized2, 3);
    
    $structuralSimilarity = jaccardSimilarity($shingles1, $shingles2);
    
    // Also compare token sequence (for logic flow)
    $tokens1 = getCodeTokens($code1);
    $tokens2 = getCodeTokens($code2);
    $tokenSimilarity = sequenceSimilarity($tokens1, $tokens2);
    
    // Weighted combination: 60% structure, 40% token sequence
    return round(($structuralSimilarity * 0.6) + ($tokenSimilarity * 0.4), 2);
}

/**
 * Get word frequency array from text
 */
function getWordFrequency($text) {
    $words = preg_split('/\s+/', strtolower($text));
    $freq = [];
    foreach ($words as $word) {
        if (strlen($word) > 2) {  // Ignore very short words
            $freq[$word] = ($freq[$word] ?? 0) + 1;
        }
    }
    return $freq;
}

/**
 * Calculate similarity between word frequency arrays
 */
function wordFrequencySimilarity($freq1, $freq2) {
    if (empty($freq1) || empty($freq2)) return 0;
    
    $common = 0;
    $total = 0;
    
    foreach ($freq1 as $word => $count1) {
        $count2 = $freq2[$word] ?? 0;
        $common += min($count1, $count2);
        $total += max($count1, $count2);
    }
    
    foreach ($freq2 as $word => $count2) {
        if (!isset($freq1[$word])) {
            $total += $count2;
        }
    }
    
    return $total > 0 ? ($common / $total) * 100 : 0;
}

/**
 * Remove comments from code
 */
function removeComments($code) {
    // Remove single-line comments
    $code = preg_replace('/\/\/.*$/', '', $code);
    // Remove multi-line comments
    $code = preg_replace('/\/\*.*?\*\//s', '', $code);
    // Remove Python/other single-line comments
    $code = preg_replace('/#.*$/', '', $code);
    return $code;
}

/**
 * Normalize code by replacing variable/function names with placeholders
 * This helps detect structural similarity even with different naming
 */
function normalizeCode($code) {
    // Replace variable names ($var, $variable) with $VAR
    $code = preg_replace('/\$[a-zA-Z_][a-zA-Z0-9_]*/', '$VAR', $code);
    
    // Replace Python/JavaScript variables (no $ sign)
    $code = preg_replace('/(?<!\w)[a-z_][a-z0-9_]*(?=\s*=)/i', 'VAR', $code);
    $code = preg_replace('/(?<!\w)[a-z_][a-z0-9_]*(?=\s*\+)/i', 'VAR', $code);
    
    // Keep keywords
    $keywords = ['if', 'else', 'for', 'while', 'do', 'switch', 'case', 'break', 
                 'continue', 'return', 'function', 'class', 'public', 'private', 
                 'protected', 'static', 'const', 'include', 'require', 'echo', 
                 'print', 'def', 'import', 'from', 'try', 'except', 'finally',
                 'with', 'as', 'lambda', 'yield', 'assert', 'pass'];
    
    // Replace user-defined function calls (not keywords)
    $code = preg_replace_callback('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', function($matches) use ($keywords) {
        if (!in_array($matches[1], $keywords)) {
            return 'FUNC(';
        }
        return $matches[0];
    }, $code);
    
    // Normalize numbers to NUM
    $code = preg_replace('/\b\d+\b/', 'NUM', $code);
    
    // Normalize strings to STR
    $code = preg_replace('/"[^"]*"/', 'STR', $code);
    $code = preg_replace("/'[^']*'/", 'STR', $code);
    
    return $code;
}

/**
 * Extract code tokens (operators, keywords, structure)
 */
function getCodeTokens($code) {
    // Remove strings and comments first
    $code = preg_replace('/"[^"]*"/', 'STR', $code);
    $code = preg_replace("/'[^']*'/", 'STR', $code);
    $code = removeComments($code);
    
    // Extract tokens (operators and keywords are most important)
    preg_match_all('/\b(if|else|for|while|do|switch|case|break|continue|return|function|class|public|private|protected|static|const|new|this|parent|self|def|import|try|except)\b|[\{\}\(\)\[\]\=\+\-\*\/\%\!\&\|\<\>]/', $code, $matches);
    
    return $matches[0];
}

/**
 * Calculate sequence similarity (for code logic flow)
 */
function sequenceSimilarity($seq1, $seq2) {
    if (empty($seq1) || empty($seq2)) return 0;
    
    // Simple longest common subsequence length
    $len1 = count($seq1);
    $len2 = count($seq2);
    
    $dp = array_fill(0, $len1 + 1, array_fill(0, $len2 + 1, 0));
    
    for ($i = 1; $i <= $len1; $i++) {
        for ($j = 1; $j <= $len2; $j++) {
            if ($seq1[$i-1] === $seq2[$j-1]) {
                $dp[$i][$j] = $dp[$i-1][$j-1] + 1;
            } else {
                $dp[$i][$j] = max($dp[$i-1][$j], $dp[$i][$j-1]);
            }
        }
    }
    
    $lcs = $dp[$len1][$len2];
    return ($lcs / max($len1, $len2)) * 100;
}

/**
 * Get risk level from similarity score
 */
function getRiskLevel($similarity, $type = 'essay') {
    if ($type == 'code') {
        if ($similarity >= 50) return 'High Risk';
        if ($similarity >= 30) return 'Attention Required';
        return 'Verified';
    } else {
        if ($similarity >= 40) return 'High Risk';
        if ($similarity >= 25) return 'Attention Required';
        return 'Verified';
    }
}

/**
 * Get recommended grade based on similarity
 */
function getRecommendedGrade($similarity, $type = 'essay') {
    if ($type == 'code') {
        if ($similarity < 30) return 'A';
        if ($similarity < 50) return 'B';
        if ($similarity < 65) return 'C';
        if ($similarity < 80) return 'D';
        return 'F';
    } else {
        if ($similarity < 20) return 'A';
        if ($similarity < 35) return 'B';
        if ($similarity < 55) return 'C';
        if ($similarity < 75) return 'D';
        return 'F';
    }
}

/**
 * Highlight matching text between two submissions
 * @param string $text1 Text to highlight
 * @param string $text2 Source text to compare against
 * @param string $lecturer_text Template to ignore
 * @return string HTML with highlighted matches
 */
function highlightMatches($text1, $text2, $lecturer_text = '') {
    // Remove lecturer template
    if (!empty($lecturer_text)) {
        $text1 = str_replace($lecturer_text, '', $text1);
        $text2 = str_replace($lecturer_text, '', $text2);
    }
    
    // Get 4-word shingles from text2
    $shingles = getShingles($text2, 4);
    
    // Sort by length (longest first) to avoid overlapping
    usort($shingles, function($a, $b) {
        return strlen($b) - strlen($a);
    });
    
    $highlighted = htmlspecialchars($text1);
    
    foreach ($shingles as $shingle) {
        $pattern = '/' . preg_quote(htmlspecialchars($shingle), '/') . '/i';
        $replacement = '<span class="similarity-highlight" title="Matches another submission">$0</span>';
        $highlighted = preg_replace($pattern, $replacement, $highlighted);
    }
    
    return nl2br($highlighted);
}
?>