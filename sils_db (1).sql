-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3301
-- Generation Time: Jun 23, 2026 at 07:23 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sils_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `assignment`
--

CREATE TABLE `assignment` (
  `assignment_id` int(11) NOT NULL,
  `tittle` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `due_date` datetime NOT NULL,
  `type` enum('essay','code') NOT NULL,
  `class_id` int(11) NOT NULL,
  `lecturer_id` int(11) DEFAULT 1,
  `group_id` int(11) DEFAULT NULL,
  `reference_file` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_completed` tinyint(1) DEFAULT 0,
  `is_visible_to_all` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignment`
--

INSERT INTO `assignment` (`assignment_id`, `tittle`, `description`, `start_date`, `due_date`, `type`, `class_id`, `lecturer_id`, `group_id`, `reference_file`, `created_at`, `is_completed`, `is_visible_to_all`) VALUES
(1, 'Introduction to Algorithms', 'Write a short essay explaining what an algorithm is, its characteristics, and provide two real-life examples. Minimum 500 words.', '2026-02-01 08:00:00', '2026-02-08 23:59:00', 'essay', 1, 2, NULL, NULL, '2026-01-31 23:00:00', 1, 1),
(2, 'Basic Python Program', 'Write a Python program that accepts user input for two numbers and outputs their sum, difference, product, and quotient. Handle division by zero.', '2026-02-15 08:00:00', '2026-02-22 23:59:00', 'code', 1, 2, NULL, NULL, '2026-02-13 23:00:00', 1, 1),
(3, 'Control Flow in Programming', 'Discuss the importance of control flow (if-else, loops) in programming with examples from real applications. Min 600 words.', '2026-03-01 08:00:00', '2026-03-10 23:59:00', 'essay', 1, 4, NULL, NULL, '2026-02-28 23:00:00', 1, 0),
(4, 'HTML & CSS Reflection', 'Write a reflection on what you have learned about HTML and CSS so far. Include examples of elements you have used and challenges you faced.', '2026-02-10 08:00:00', '2026-02-17 23:59:00', 'essay', 2, 5, NULL, NULL, '2026-02-08 23:00:00', 1, 1),
(5, 'Build a Personal Portfolio Page', 'Create a personal portfolio webpage using HTML5 and CSS3. Must include: header, navigation, about section, skills section, and footer.', '2026-03-01 08:00:00', '2026-03-15 23:59:00', 'code', 2, 5, NULL, NULL, '2026-02-27 23:00:00', 1, 1),
(6, 'Responsive Design Principles', 'Explain the concept of responsive web design. Discuss the role of media queries, flexible grids, and CSS frameworks like Bootstrap.', '2026-03-20 08:00:00', '2026-03-28 23:59:00', 'essay', 2, 2, NULL, NULL, '2026-03-19 23:00:00', 1, 0),
(7, 'OOP Concepts Essay', 'Explain the four pillars of Object-Oriented Programming (encapsulation, abstraction, inheritance, polymorphism) with code examples.', '2026-02-05 08:00:00', '2026-02-12 23:59:00', 'essay', 3, 4, NULL, NULL, '2026-02-03 23:00:00', 1, 1),
(8, 'Student Record System', 'Implement a simple Student Record System using OOP in Java or Python. Include at least 3 classes with proper inheritance.', '2026-03-10 08:00:00', '2026-03-24 23:59:00', 'code', 3, 5, NULL, NULL, '2026-03-08 23:00:00', 1, 1),
(9, 'Introduction to Databases', 'Describe the differences between flat-file systems and relational databases. Why are relational databases preferred in modern applications?', '2026-02-08 08:00:00', '2026-02-15 23:59:00', 'essay', 11, 7, NULL, NULL, '2026-02-06 23:00:00', 1, 1),
(10, 'ER Diagram Design', 'Design an Entity-Relationship (ER) diagram for a Library Management System. Submit your diagram and a brief explanation of your design decisions.', '2026-03-01 08:00:00', '2026-03-14 23:59:00', 'essay', 11, 8, NULL, NULL, '2026-02-27 23:00:00', 1, 1),
(11, 'Normalization Essay', 'Explain the concept of database normalization. Describe 1NF, 2NF, and 3NF with examples. Why is normalization important?', '2026-02-12 08:00:00', '2026-02-20 23:59:00', 'essay', 12, 6, NULL, NULL, '2026-02-10 23:00:00', 1, 1),
(12, 'Schema Design for E-Commerce', 'Design a normalized relational schema for a simple e-commerce system. Include tables for products, customers, orders, and order items.', '2026-03-15 08:00:00', '2026-03-30 23:59:00', 'essay', 12, 9, NULL, NULL, '2026-03-13 23:00:00', 1, 1),
(13, 'SQL Basics Reflection', 'Reflect on your learning journey with SQL so far. Discuss SELECT, INSERT, UPDATE, DELETE with examples from your practice sessions.', '2026-02-20 08:00:00', '2026-02-27 23:59:00', 'essay', 13, 10, NULL, NULL, '2026-02-18 23:00:00', 1, 1),
(14, 'SQL Query Assignment', 'Using the provided student database, write SQL queries to: (1) list all students, (2) find students with GPA > 3.5, (3) count students per program, (4) find the average GPA.', '2026-03-05 08:00:00', '2026-03-15 23:59:00', 'code', 13, 10, NULL, NULL, '2026-03-03 23:00:00', 1, 1),
(15, 'AI in Education: Benefits and Challenges', 'Discuss the role of artificial intelligence in modern education. Analyze both the benefits and potential drawbacks. Min 800 words.', '2026-06-01 08:00:00', '2026-06-15 23:59:00', 'essay', 2, 5, NULL, NULL, '2026-05-29 23:00:00', 0, 1),
(16, 'Final Project: Dynamic Website', 'Build a fully functional dynamic website using HTML, CSS, JavaScript, and PHP with a MySQL backend. Include user authentication.', '2026-05-20 08:00:00', '2026-06-20 23:59:00', 'code', 2, 2, NULL, NULL, '2026-05-17 23:00:00', 0, 1),
(17, 'Big Data Trends Essay', 'Research and write about three major trends in big data analytics (e.g., real-time processing, machine learning integration, cloud storage).', '2026-06-05 08:00:00', '2026-06-20 23:59:00', 'essay', 13, 10, NULL, NULL, '2026-06-03 23:00:00', 0, 1),
(18, 'ASSIGNMENT INDIVIDU', 'TESTINGG', '2026-06-23 13:24:00', '2026-06-22 13:31:00', 'essay', 7, 11, NULL, NULL, '2026-06-22 05:24:13', 1, 0),
(19, 'TEST CODING', 'TESTTINGG', '2026-06-22 15:07:00', '2026-06-22 15:08:00', 'code', 7, 11, NULL, NULL, '2026-06-22 07:06:41', 1, 0),
(21, 'CODE ASSIGNMENT', 'WRITE FULL CODE', '2026-06-22 22:47:00', '2026-06-22 22:53:00', 'code', 7, 12, NULL, NULL, '2026-06-22 14:46:44', 1, 0),
(22, 'RPEORT PROJECT', 'WRITE REPORT', '2026-06-22 22:57:00', '2026-06-22 23:06:00', 'essay', 7, 13, NULL, '[{\"original_name\":\"RUBRIC_GRADE.txt\",\"server_path\":\"uploads\\/references\\/6a394d0ea2555_RUBRIC_GRADE.txt\"}]', '2026-06-22 14:56:14', 1, 0),
(23, 'SASASA', 'SASASAS', '2026-06-23 15:28:00', '2026-06-23 15:30:00', 'essay', 7, 12, NULL, NULL, '2026-06-23 07:27:08', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `chat_log`
--

CREATE TABLE `chat_log` (
  `chat_id` int(11) NOT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `student_question` text NOT NULL,
  `ai_response` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `student_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_log`
--

INSERT INTO `chat_log` (`chat_id`, `session_id`, `student_question`, `ai_response`, `created_at`, `student_id`) VALUES
(1, 'sess_ahmad_001', 'What is the difference between a list and a tuple in Python?', 'A list is mutable (can be changed after creation) and uses square brackets: [1, 2, 3]. A tuple is immutable (cannot be changed) and uses parentheses: (1, 2, 3). Use tuples for data that should not change, like coordinates, and lists when you need to add, remove, or modify items.', '2026-02-10 01:00:00', 1),
(2, 'sess_nurul_001', 'How do I center a div in CSS?', 'The modern way is using flexbox. On the parent element: display: flex; justify-content: center; align-items: center;. This centers the child div both horizontally and vertically. Alternatively, use margin: 0 auto; with a defined width for horizontal centering only.', '2026-02-18 03:00:00', 2),
(3, 'sess_farah_001', 'What does PRIMARY KEY mean in SQL?', 'A PRIMARY KEY is a column (or group of columns) that uniquely identifies each row in a table. It must be unique and cannot be NULL. Every table should have a primary key. Example: student_id INT PRIMARY KEY AUTO_INCREMENT. This means each student gets a unique automatically incremented ID.', '2026-02-20 07:00:00', 11),
(4, 'sess_mohd_001', 'What is the difference between DELETE and TRUNCATE in SQL?', 'DELETE removes rows one by one and can be rolled back if inside a transaction. You can use WHERE to delete specific rows: DELETE FROM student WHERE student_id = 5. TRUNCATE removes all rows at once, is faster, but cannot be rolled back in most databases and resets AUTO_INCREMENT counters. Use DELETE when you need fine control, TRUNCATE when clearing an entire table quickly.', '2026-02-22 08:00:00', 12),
(5, 'sess_haziq_001', 'What is polymorphism in OOP?', 'Polymorphism means \"many forms\". In OOP, it allows objects of different classes to be treated as objects of a common parent class. The most common form is method overriding, where a child class provides its own implementation of a method defined in the parent. Example: Animal has a speak() method. Dog overrides it to return \"Woof\" and Cat overrides it to return \"Meow\". You can call animal.speak() on any Animal and get the correct sound.', '2026-03-06 02:00:00', 3);

-- --------------------------------------------------------

--
-- Table structure for table `chat_message`
--

CREATE TABLE `chat_message` (
  `message_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `sender` enum('student','ai') NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_message`
--

INSERT INTO `chat_message` (`message_id`, `session_id`, `student_id`, `sender`, `message`, `created_at`) VALUES
(1, 1, 1, 'student', 'Can you explain the difference between for loops and while loops in Python?', '2026-02-18 02:00:00'),
(2, 1, 1, 'ai', 'Sure! A for loop in Python is used when you know in advance how many times you want to iterate, such as going through each item in a list. Example: for item in [1,2,3]: print(item). A while loop runs as long as a condition remains True, useful when you don\'t know the number of iterations in advance. Example: while x > 0: x -= 1. Use for when iterating over a collection, and while for condition-based repetition.', '2026-02-18 02:01:00'),
(3, 1, 1, 'student', 'What about nested loops? When should I use them?', '2026-02-18 02:05:00'),
(4, 1, 1, 'ai', 'Nested loops are loops inside other loops. They are useful for working with 2D data structures like matrices or tables. For example, to print a multiplication table: for i in range(1,4): for j in range(1,4): print(i*j, end=\" \"). The outer loop runs 3 times, and for each outer iteration, the inner loop also runs 3 times, giving 9 total iterations. Be careful with nested loops as they can be slow for large datasets with O(n²) or worse complexity.', '2026-02-18 02:06:00'),
(5, 1, 1, 'student', 'Thank you! That is very clear.', '2026-02-18 02:25:00'),
(6, 8, 11, 'student', 'I am confused about the different types of SQL JOINs. Can you explain them?', '2026-02-22 02:00:00'),
(7, 8, 11, 'ai', 'Of course! There are four main JOIN types. INNER JOIN returns only rows where there is a match in both tables. LEFT JOIN returns all rows from the left table plus matched rows from the right table (nulls for non-matches). RIGHT JOIN is the opposite of LEFT JOIN. FULL OUTER JOIN returns all rows from both tables with nulls where no match exists. Example: SELECT s.full_name, c.class_name FROM student s INNER JOIN enrollment e ON s.student_id = e.student_id INNER JOIN class c ON e.class_id = c.class_id. This returns students with their classes.', '2026-02-22 02:01:00'),
(8, 8, 11, 'student', 'What if a student has no enrollment? Which JOIN should I use?', '2026-02-22 02:10:00'),
(9, 8, 11, 'ai', 'Use a LEFT JOIN! With LEFT JOIN, all students appear in the results even if they have no enrollment. The class_name would be NULL for those students. Query: SELECT s.full_name, c.class_name FROM student s LEFT JOIN enrollment e ON s.student_id = e.student_id LEFT JOIN class c ON e.class_id = c.class_id. This is useful for finding students who are not yet enrolled by adding WHERE c.class_id IS NULL at the end.', '2026-02-22 02:12:00'),
(10, 8, 11, 'student', 'Great explanation! Now I understand JOINs much better.', '2026-02-22 02:40:00'),
(11, 9, 12, 'student', 'Can you give me a simple example of 2NF violation and how to fix it?', '2026-02-28 06:00:00'),
(12, 9, 12, 'ai', 'Sure! Consider a table: OrderDetails(OrderID, ProductID, ProductName, Quantity). The primary key is (OrderID, ProductID). ProductName depends only on ProductID, not on the full composite key. This is a partial dependency, which violates 2NF. Fix: split into two tables. Orders: OrderDetails(OrderID, ProductID, Quantity). Products: Product(ProductID, ProductName). Now ProductName is in the Products table where ProductID is the full primary key. No partial dependency exists.', '2026-02-28 06:01:00'),
(13, 9, 12, 'student', 'And what about 3NF? How does transitive dependency look?', '2026-02-28 06:15:00'),
(14, 9, 12, 'ai', 'Transitive dependency means: non-key column A → non-key column B. Example: Student(StudentID, DeptID, DeptName). StudentID is the primary key. StudentID → DeptID and DeptID → DeptName, so StudentID → DeptName transitively. DeptName depends on DeptID, not directly on StudentID. Fix: create a Department(DeptID, DeptName) table. Now Student only has (StudentID, DeptID) and DeptName lives in the Department table with DeptID as its own primary key.', '2026-02-28 06:20:00'),
(15, 9, 12, 'student', 'This makes so much sense now. Thank you!', '2026-02-28 06:50:00'),
(16, 21, 1, 'student', 'WRITE ME AN ESSAY ABOUT AI', '2026-06-22 05:45:21'),
(17, 21, 1, 'ai', 'To get started on your essay about AI, consider exploring the concept of machine learning and how it enables AI systems to learn from data and improve their performance over time. You might also think about the potential applications of AI in various industries, such as healthcare or transportation, and the ethical implications that come with these advancements. \n\nNow try applying this to your problem: What specific aspect of AI would you like to focus on in your essay, and how can you use real-world examples to support your arguments?', '2026-06-22 05:45:22'),
(18, 21, 1, 'student', '1. AI in Healthcare & DiagnosticsThe Focus: Evaluating whether AI improves diagnostic accuracy or if it introduces dangerous blind spots due to algorithmic bias.Real-World Example: Discuss how the FDA has approved AI-powered radiology tools for detecting diseases like diabetic retinopathy, but contrast this with studies showing that these same models can be less accurate on underrepresented patient demographics.', '2026-06-22 05:47:03'),
(19, 21, 1, 'ai', 'To explore the impact of AI in healthcare and diagnostics, consider the trade-off between improved diagnostic accuracy and potential algorithmic bias. For instance, AI-powered radiology tools can effectively detect diseases like diabetic retinopathy, but their performance may vary across different patient demographics. Now try writing your own version based on this example:', '2026-06-22 05:47:03'),
(20, 22, 21, 'student', 'Can you help me write an essay about artificial intelligence in education?', '2026-06-23 15:21:31'),
(21, 22, 21, 'ai', 'To get started on your essay about artificial intelligence in education, consider exploring how AI can enhance personalized learning experiences, such as adaptive assessments or intelligent tutoring systems. You might also think about the potential benefits and drawbacks of implementing AI in educational settings, like increased efficiency versus concerns about bias in AI algorithms. Now try applying this to your problem: What specific aspect of AI in education will you focus on in your essay?', '2026-06-23 15:21:31'),
(22, 22, 21, 'student', 'I want to write about personalized learning. I think AI can adapt to each student\'s needs.', '2026-06-23 15:22:29'),
(23, 22, 21, 'ai', 'Personalized learning is an exciting concept that can be thought of as a tailored suit, where the educational approach is customized to fit each student\'s unique needs and learning style, much like how AI can adapt to individual preferences in music or movie recommendations. You could explore how AI-powered tools can help identify knowledge gaps, learning pace, and interests to create a more effective learning experience. How would you apply this idea to your situation?', '2026-06-23 15:22:30'),
(24, 22, 21, 'student', 'I think AI can help students who are falling behind by giving them extra practice. And for advanced students, they can move ahead faster.', '2026-06-23 15:23:01'),
(25, 22, 21, 'ai', 'That\'s a great idea, using AI to provide personalized learning experiences can be incredibly beneficial for students of all levels. For instance, a simple Python program can be designed to generate practice problems based on a student\'s weaknesses or strengths. \n\nHere\'s a small example of how this could work in code:\n```\ndef generate_practice_problems(student_level):\n    if student_level == \"beginner\":\n        # generate simple problems\n        return [\"problem1\", \"problem2\"]\n    elif student_level == \"advanced\":\n        # generate more complex problems\n        return [\"problem3\", \"problem4\"]\n```\nRemember to consider the student\'s current level and adapt the difficulty of the practice problems accordingly. Now try writing your own version based on this example.', '2026-06-23 15:23:02'),
(26, 22, 21, 'student', 'The AI could use quizzes to find what they don\'t understand. But I think teachers should still be involved because AI can\'t really connect with students emotionally.', '2026-06-23 15:23:43'),
(27, 22, 21, 'ai', 'That\'s a thoughtful perspective, recognizing the limitations of AI in building emotional connections with students. It\'s almost like having a highly efficient study guide, but missing the guidance of a mentor who can offer empathy and support. How would you apply this idea to your situation, where you think a balance between AI-driven assessments and human teaching could be most effective?', '2026-06-23 15:23:43'),
(28, 22, 21, 'student', 'I think AI would help teachers by grading assignments faster, so they have more time to help students one-on-one.', '2026-06-23 15:25:15'),
(29, 22, 21, 'ai', 'That\'s a great start, thinking about how AI can assist with tasks like grading can indeed free up teachers to focus on more personalized support. It\'s similar to how a calculator can help with math problems, allowing you to focus on the bigger picture. Consider exploring other ways AI can aid in education, such as creating customized learning plans or offering real-time feedback, and think about how these tools can enhance the teacher-student relationship. How would you apply this idea to your situation?', '2026-06-23 15:25:16'),
(30, 22, 21, 'student', 'Actually, I\'ve seen tools that check grammar and give feedback on essays. They\'re helpful but sometimes they don\'t understand context.', '2026-06-23 15:26:03'),
(31, 22, 21, 'ai', 'Those tools can be useful, but they often lack the nuance of human understanding, similar to how a GPS might provide directions but not account for unexpected road closures. Think of them as a guide, rather than a replacement, for human feedback and editing. How would you apply this idea to your situation?', '2026-06-23 15:26:04'),
(32, 22, 21, 'student', 'Maybe students should get AI feedback first, then a teacher checks the important parts.', '2026-06-23 15:26:30'),
(33, 22, 21, 'ai', 'That\'s an interesting idea, using AI as a preliminary feedback tool to help students refine their work before a teacher reviews it. It\'s a bit like having a personal editor or tutor, helping to catch initial mistakes and suggest improvements. How would you apply this idea to your situation?', '2026-06-23 15:26:31'),
(34, 22, 21, 'student', 'Hmm, the problem might be that some teachers don\'t understand AI enough to know when to trust it.', '2026-06-23 15:27:02'),
(35, 22, 21, 'ai', 'That\'s a great point, it\'s almost like trying to navigate a map without knowing how to read it - you need to understand the tool to use it effectively. Think of AI like a highly advanced calculator, it can process vast amounts of information, but it\'s only as good as the data it\'s given and the person interpreting the results. How would you apply this idea to your situation?', '2026-06-23 15:27:03'),
(36, 22, 21, 'student', 'Okay, I think I have enough ideas now. I\'ll write an essay and maybe ask you to review it.', '2026-06-23 15:27:23'),
(37, 22, 21, 'ai', 'That sounds like a great plan. As you start writing, consider organizing your thoughts into an introduction that sets up the main argument, body paragraphs that provide evidence and analysis, and a conclusion that ties everything together. Think about how you can use transitions to connect your ideas and make your essay flow smoothly. Now try applying this to your problem: what\'s the main argument or thesis statement that you want to make in your essay?', '2026-06-23 15:27:24'),
(38, 23, 21, 'student', 'Can you help me write an essay about artificial intelligence in education?', '2026-06-23 15:35:14'),
(39, 23, 21, 'ai', 'To write an essay about artificial intelligence in education, let\'s start by breaking down the topic into smaller sections. You could explore how AI is being used to personalize learning, automate grading, and enhance student engagement.\n\nThink of it like a teacher\'s assistant, where AI can help with tasks such as providing real-time feedback, offering customized learning plans, and even detecting early signs of learning difficulties. For example, AI-powered chatbots can be used to support students with their queries, similar to how a virtual teaching assistant might help a teacher with administrative tasks.\n\nConsider the following structure for your essay: introduction to AI in education, benefits and challenges, current applications, and future possibilities. You could also discuss the potential drawbacks, such as job displacement for teachers or the risk of biased algorithms.\n\nNow try applying this to your problem: What specific aspect of AI in education would you like to focus on in your essay, and how can you use a real-life example to illustrate its impact on students and teachers?', '2026-06-23 15:35:15'),
(40, 23, 21, 'student', 'I think AI can help students who are falling behind by giving them extra practice. And for advanced students, they can move ahead faster.', '2026-06-23 15:35:42'),
(41, 23, 21, 'ai', 'AI can indeed provide personalized support to students, helping those who are struggling to catch up and allowing advanced students to accelerate their learning. \n\nTo demonstrate this concept, let\'s consider a simple example: a math practice program. The program can assess a student\'s current level of understanding and provide tailored exercises to help them improve. For instance, if a student is having trouble with multiplication, the program can generate a set of multiplication problems for them to practice.\n\nHere\'s a small working code snippet in Python that demonstrates this idea:\n```python\ndef math_practice(level):\n    if level == \"beginner\":\n        print(\"Here are some basic multiplication problems:\")\n        for i in range(5):\n            num1 = 2\n            num2 = i + 1\n            print(f\"What is {num1} x {num2}?\")\n    elif level == \"advanced\":\n        print(\"Here are some challenging multiplication problems:\")\n        for i in range(5):\n            num1 = 5\n            num2 = i + 6\n            print(f\"What is {num1} x {num2}?\")\n\nmath_practice(\"beginner\")\nmath_practice(\"advanced\")\n```\nImportant syntax to remember in this example is the use of conditional statements (`if` and `elif`) to determine which set of exercises to generate based on the student\'s level. The `for` loop is also used to generate multiple problems.\n\nNow try writing your own version based on this example.', '2026-06-23 15:35:44'),
(42, 23, 21, 'student', 'The AI could use quizzes to find what they don\'t understand. But I think teachers should still be involved because AI can\'t really connect with students emotionally.', '2026-06-23 15:36:21'),
(43, 23, 21, 'ai', 'That\'s a great point about the importance of human connection in learning. AI can be a useful tool for identifying knowledge gaps, but it\'s the teachers who can provide empathy, guidance, and support to help students overcome those gaps. What do you think would be the ideal balance between using AI for assessment and having teachers provide emotional support and guidance?', '2026-06-23 15:36:21'),
(44, 23, 21, 'student', 'I think AI would help teachers by grading assignments faster, so they have more time to help students one-on-one.', '2026-06-23 15:36:43'),
(45, 23, 21, 'ai', 'That\'s a great start, focusing on how AI can automate tasks like grading to free up teachers\' time. You might also consider how AI can help with personalized learning, such as adapting lesson plans to individual students\' needs or providing real-time feedback. What other ways do you think AI could support teachers in creating a more tailored and effective learning experience for their students?', '2026-06-23 15:36:44'),
(46, 23, 21, 'student', 'Okay, I think I have enough ideas now. I\'ll write an essay and maybe ask you to review it.', '2026-06-23 15:37:05'),
(47, 23, 21, 'ai', 'It sounds like you\'ve got a good starting point. When writing your essay, consider creating an outline to organize your thoughts and ensure a clear structure. Think about introducing your main idea, supporting it with evidence or examples, and then concluding with a summary of your key points.\n\nAs you write, ask yourself: what\'s the main argument or point I want to get across, and how can I use evidence to back it up? \n\nNow, go ahead and start writing your essay, and then feel free to share it with me for feedback - what\'s the first paragraph of your essay going to be about?', '2026-06-23 15:37:06'),
(48, 24, 21, 'student', 'write me python code', '2026-06-23 15:38:37'),
(49, 24, 21, 'ai', 'Let\'s get started with a basic example. Here\'s a simple Python script that prints out a message:\n```python\n# This is a comment - anything after the \"#\" symbol is ignored\nprint(\"Hello, world!\")  # This line prints out a message\n```\nThink about what you want your code to do. Do you want to:\n\n* Ask the user for input?\n* Perform a calculation?\n* Work with a list or dictionary?\n\nConsider using a `for` loop or an `if` statement to control the flow of your program. For example:\n```python\n# A simple loop that prints out numbers from 1 to 5\nfor i in range(1, 6):\n    print(i)\n```\nNow try applying this to your problem: What\'s the first line of code you think you\'ll need to write?', '2026-06-23 15:38:39'),
(50, 24, 21, 'student', 'i think i want Ask the user for input?', '2026-06-23 15:39:42'),
(51, 24, 21, 'ai', 'You want to know how to ask the user for input in your code. \n\nThe concept is simple: you use a built-in function to pause your program and wait for the user to type something in. This input can then be stored in a variable and used later in your code.\n\nHere\'s a simple example: let\'s say you want to ask the user for their name. \n\n```python\n# Ask the user for their name\nname = input(\"What is your name? \")\n\n# Print out a greeting with their name\nprint(\"Hello, \" + name + \"!\")\n```\n\nImportant syntax to remember: the `input()` function is used to get user input, and it takes a string as an argument (this string is the prompt that is displayed to the user). The result of `input()` is a string, so if you need a number, you\'ll need to convert it using `int()` or `float()`.\n\nFor example, if you want to ask the user for their age, you would use `int(input(\"How old are you? \"))` to convert the input to an integer.\n\nNow try writing your own version based on this example.', '2026-06-23 15:39:44');

-- --------------------------------------------------------

--
-- Table structure for table `chat_session`
--

CREATE TABLE `chat_session` (
  `session_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `session_title` varchar(200) DEFAULT 'New Conversation',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_session`
--

INSERT INTO `chat_session` (`session_id`, `student_id`, `session_title`, `created_at`, `updated_at`) VALUES
(1, 1, 'Help with Python loops', '2026-02-18 02:00:00', '2026-02-18 02:25:00'),
(2, 1, 'Understanding recursion', '2026-02-25 06:00:00', '2026-02-25 06:40:00'),
(3, 2, 'CSS Flexbox questions', '2026-02-20 01:00:00', '2026-02-20 01:30:00'),
(4, 3, 'OOP inheritance explained', '2026-03-05 03:00:00', '2026-03-05 03:45:00'),
(5, 4, 'How to use CSS Grid', '2026-03-08 07:00:00', '2026-03-08 07:35:00'),
(6, 7, 'JavaScript event handling', '2026-03-12 12:00:00', '2026-03-12 12:50:00'),
(7, 9, 'Python list comprehension', '2026-03-15 05:00:00', '2026-03-15 05:30:00'),
(8, 11, 'SQL JOIN types', '2026-02-22 02:00:00', '2026-02-22 02:40:00'),
(9, 12, 'How does normalization work', '2026-02-28 06:00:00', '2026-02-28 06:50:00'),
(10, 13, 'Understanding foreign keys', '2026-03-02 01:00:00', '2026-03-02 01:35:00'),
(11, 14, 'ER diagram help', '2026-03-06 08:00:00', '2026-03-06 08:45:00'),
(12, 15, 'Difference between WHERE and HAVING', '2026-03-10 03:00:00', '2026-03-10 03:30:00'),
(13, 16, 'What is a primary key', '2026-03-14 12:00:00', '2026-03-14 12:25:00'),
(14, 18, 'SQL aggregate functions help', '2026-03-18 01:00:00', '2026-03-18 01:40:00'),
(15, 20, 'Indexes in SQL', '2026-03-20 06:00:00', '2026-03-20 06:30:00'),
(16, 1, 'Review for final exam', '2026-05-10 02:00:00', '2026-05-10 03:00:00'),
(17, 5, 'PHP vs JavaScript for web dev', '2026-05-15 06:00:00', '2026-05-15 06:30:00'),
(18, 8, 'Bootstrap grid system', '2026-05-20 08:00:00', '2026-05-20 08:45:00'),
(19, 11, 'Big data technologies overview', '2026-06-01 01:00:00', '2026-06-01 01:50:00'),
(20, 17, 'Data warehouse vs data lake', '2026-06-05 07:00:00', '2026-06-05 07:40:00'),
(21, 1, 'WRITE ME AN ESSAY ABOUT AI', '2026-06-22 05:45:21', '2026-06-22 05:47:03'),
(22, 21, 'Can you help me write an essay about artificial in...', '2026-06-23 15:21:31', '2026-06-23 15:27:24'),
(23, 21, 'Can you help me write an essay about artificial in...', '2026-06-23 15:35:14', '2026-06-23 15:37:06'),
(24, 21, 'write me python code', '2026-06-23 15:38:37', '2026-06-23 15:39:44');

-- --------------------------------------------------------

--
-- Table structure for table `class`
--

CREATE TABLE `class` (
  `class_id` int(11) NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `class_code` varchar(20) NOT NULL,
  `group_name` varchar(50) DEFAULT NULL,
  `max_students` int(11) DEFAULT 50,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `coordinator_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class`
--

INSERT INTO `class` (`class_id`, `class_name`, `class_code`, `group_name`, `max_students`, `created_at`, `coordinator_id`) VALUES
(1, 'Programming Fundamentals', 'BITS1013', 'BITS-SW1', 40, '2026-01-03 00:00:00', 1),
(2, 'Web Development Basics', 'BITS1023', 'BITS-SW1', 40, '2026-01-03 00:00:00', 1),
(3, 'Object-Oriented Programming', 'BITS1033', 'BITS-SW1', 40, '2026-01-03 00:00:00', 3),
(4, 'Database Systems', 'BITS1043', 'BITS-SW1', 40, '2026-01-03 00:00:00', 3),
(5, 'Mobile Application Development', 'BITS2013', 'BITS-SW2', 35, '2026-01-03 00:00:00', 1),
(6, 'Software Engineering', 'BITS2023', 'BITS-SW2', 35, '2026-01-03 00:00:00', 3),
(7, 'Advanced Web Programming', 'BITS2033', 'BITS-SW2', 35, '2026-01-03 00:00:00', 11),
(8, 'System Analysis and Design', 'BITS2043', 'BITS-SW2', 35, '2026-01-03 00:00:00', 3),
(9, 'Software Project Management', 'BITS3013', 'BITS-SW3', 30, '2026-01-03 00:00:00', 1),
(10, 'Emerging Technologies', 'BITS3023', 'BITS-SW3', 30, '2026-01-03 00:00:00', 3),
(11, 'Database Fundamentals', 'BITD1013', 'BITD-DB1', 40, '2026-01-03 00:00:00', 6),
(12, 'Data Modeling', 'BITD1023', 'BITD-DB1', 40, '2026-01-03 00:00:00', 7),
(13, 'SQL Programming', 'BITD1033', 'BITD-DB1', 40, '2026-01-03 00:00:00', 6),
(14, 'Advanced Database Systems', 'BITD2013', 'BITD-DB2', 35, '2026-01-03 00:00:00', 7),
(15, 'Big Data Analytics', 'BITD2023', 'BITD-DB2', 35, '2026-01-03 00:00:00', 6);

-- --------------------------------------------------------

--
-- Table structure for table `code_submission`
--

CREATE TABLE `code_submission` (
  `id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `code` text NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `final_grade` varchar(2) DEFAULT NULL,
  `total_score` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `code_submission`
--

INSERT INTO `code_submission` (`id`, `assignment_id`, `student_id`, `code`, `file_name`, `submitted_at`, `final_grade`, `total_score`) VALUES
(1, 2, 1, 'num1 = float(input(\"Enter first number: \"))\nnum2 = float(input(\"Enter second number: \"))\nprint(f\"Sum: {num1 + num2}\")\nprint(f\"Difference: {num1 - num2}\")\nprint(f\"Product: {num1 * num2}\")\nif num2 != 0:\n    print(f\"Quotient: {num1 / num2}\")\nelse:\n    print(\"Cannot divide by zero.\")', 'calculator_ahmad.py', '2026-02-20 06:00:00', 'A', 95.00),
(2, 2, 2, 'a = float(input(\"Number 1: \"))\nb = float(input(\"Number 2: \"))\nprint(\"Sum =\", a + b)\nprint(\"Difference =\", a - b)\nprint(\"Product =\", a * b)\nif b == 0:\n    print(\"Error: Division by zero\")\nelse:\n    print(\"Quotient =\", a / b)', 'calculator_nurul.py', '2026-02-21 02:00:00', 'A', 92.00),
(3, 2, 3, 'x = float(input(\"Enter x: \"))\ny = float(input(\"Enter y: \"))\nprint(\"x + y =\", x+y)\nprint(\"x - y =\", x-y)\nprint(\"x * y =\", x*y)\ntry:\n    print(\"x / y =\", x/y)\nexcept ZeroDivisionError:\n    print(\"Division by zero is not allowed\")', 'calculator_haziq.py', '2026-02-21 08:00:00', 'A', 90.00),
(4, 2, 4, 'n1 = float(input(\"Input first number: \"))\nn2 = float(input(\"Input second number: \"))\nprint(\"Addition:\", n1+n2)\nprint(\"Subtraction:\", n1-n2)\nprint(\"Multiplication:\", n1*n2)\nif n2 != 0:\n    print(\"Division:\", n1/n2)\nelse:\n    print(\"Division undefined (denominator is zero)\")', 'calculator_siti.py', '2026-02-20 12:00:00', 'A', 93.00),
(5, 2, 5, 'p = input(\"First num: \")\nq = input(\"Second num: \")\np = float(p)\nq = float(q)\nprint(\"Sum:\", p+q)\nprint(\"Diff:\", p-q)\nprint(\"Product:\", p*q)\nif q == 0:\n    print(\"Cannot divide by zero\")\nelse:\n    print(\"Division:\", p/q)', 'calculator_daniel.py', '2026-02-22 01:00:00', 'B', 78.00),
(6, 2, 6, 'a = input(\"Enter a: \")\nb = input(\"Enter b: \")\na = int(a)\nb = int(b)\nprint(a+b)\nprint(a-b)\nprint(a*b)\nif b != 0:\n    print(a/b)', 'calculator_amirah.py', '2026-02-22 03:00:00', 'C', 65.00),
(7, 2, 7, 'def calculate(a, b):\n    print(f\"Sum: {a+b}\")\n    print(f\"Difference: {a-b}\")\n    print(f\"Product: {a*b}\")\n    if b != 0:\n        print(f\"Quotient: {a/b:.2f}\")\n    else:\n        print(\"Error: Cannot divide by zero\")\n\nx = float(input(\"Enter first number: \"))\ny = float(input(\"Enter second number: \"))\ncalculate(x, y)', 'calculator_hafiz.py', '2026-02-21 14:00:00', 'A', 97.00),
(8, 2, 8, 'num1 = float(input(\"Enter num1: \"))\nnum2 = float(input(\"Enter num2: \"))\nsum_result = num1 + num2\ndiff_result = num1 - num2\nprod_result = num1 * num2\nprint(\"Sum:\", sum_result)\nprint(\"Difference:\", diff_result)\nprint(\"Product:\", prod_result)\nif num2 == 0:\n    print(\"Division by zero error\")\nelse:\n    print(\"Quotient:\", num1/num2)', 'calculator_liyana.py', '2026-02-22 00:00:00', 'B', 82.00),
(9, 2, 9, 'first = float(input(\"First number: \"))\nsecond = float(input(\"Second number: \"))\noperations = {\n    \"Sum\": first + second,\n    \"Difference\": first - second,\n    \"Product\": first * second\n}\nfor op, result in operations.items():\n    print(f\"{op}: {result}\")\nprint(\"Quotient:\", first/second if second != 0 else \"undefined (div by zero)\")', 'calculator_syafiq.py', '2026-02-21 05:00:00', 'A', 96.00),
(10, 2, 10, 'num1 = float(input(\"Number 1: \"))\nnum2 = float(input(\"Number 2: \"))\nprint(f\"Addition     : {num1} + {num2} = {num1+num2}\")\nprint(f\"Subtraction  : {num1} - {num2} = {num1-num2}\")\nprint(f\"Multiplication: {num1} * {num2} = {num1*num2}\")\nif num2 == 0:\n    print(\"Division     : undefined (division by zero)\")\nelse:\n    print(f\"Division     : {num1} / {num2} = {num1/num2:.4f}\")', 'calculator_farhana.py', '2026-02-22 02:00:00', 'A', 94.00),
(11, 14, 11, '-- Q1: List all students\nSELECT student_id, matric_no, full_name, program FROM student ORDER BY full_name;\n\n-- Q2: Students with GPA > 3.5\nSELECT student_id, full_name, gpa FROM student WHERE gpa > 3.5;\n\n-- Q3: Count students per program\nSELECT program, COUNT(*) AS total_students FROM student GROUP BY program;\n\n-- Q4: Average GPA\nSELECT AVG(gpa) AS average_gpa FROM student;', 'sql_queries_farah.sql', '2026-03-13 06:00:00', 'A', 94.00),
(12, 14, 12, '-- Query 1: All students\nSELECT * FROM student;\n\n-- Query 2: High GPA students\nSELECT full_name, program, gpa FROM student WHERE gpa > 3.5 ORDER BY gpa DESC;\n\n-- Query 3: Students per program\nSELECT program, COUNT(student_id) AS count FROM student GROUP BY program ORDER BY count DESC;\n\n-- Query 4: Average GPA\nSELECT ROUND(AVG(gpa), 2) AS avg_gpa FROM student;', 'sql_queries_mohd.sql', '2026-03-14 02:00:00', 'A', 91.00),
(13, 14, 13, '-- 1. List all students\nSELECT student_id, full_name, matric_no, program FROM student;\n\n-- 2. Students with GPA above 3.5\nSELECT full_name, gpa FROM student WHERE gpa > 3.5;\n\n-- 3. Student count per program\nSELECT program, COUNT(*) AS num_students FROM student GROUP BY program;\n\n-- 4. Average GPA of all students\nSELECT AVG(gpa) AS mean_gpa FROM student;', 'sql_queries_aina.sql', '2026-03-14 08:00:00', 'B', 84.00),
(14, 14, 14, 'SELECT * FROM student;\nSELECT * FROM student WHERE gpa > 3.5;\nSELECT program, count(*) FROM student GROUP BY program;\nSELECT avg(gpa) FROM student;', 'sql_queries_khairul.sql', '2026-03-13 12:00:00', 'B', 70.00),
(15, 14, 15, '-- All students\nSELECT student_id, matric_no, full_name, program, phone_no FROM student ORDER BY matric_no;\n\n-- Students with high GPA\nSELECT full_name, program, gpa FROM student WHERE gpa > 3.5 ORDER BY gpa DESC;\n\n-- Program enrollment count\nSELECT program, COUNT(*) AS student_count FROM student GROUP BY program;\n\n-- Average GPA per program\nSELECT program, ROUND(AVG(gpa),2) AS avg_gpa FROM student GROUP BY program;', 'sql_queries_noraina.sql', '2026-03-15 01:00:00', 'B', 86.00),
(16, 19, 21, 'def factorial_iterative(n):\r\n    \"\"\"\r\n    Calculate factorial using iterative approach.\r\n    Args:\r\n        n: Non-negative integer\r\n    Returns:\r\n        Factorial of n\r\n    \"\"\"\r\n    if n < 0:\r\n        raise ValueError(\"Factorial is not defined for negative numbers\")\r\n    if n == 0:\r\n        return 1\r\n    \r\n    result = 1\r\n    for i in range(2, n + 1):\r\n        result *= i\r\n    return result\r\n\r\ndef factorial_recursive(n):\r\n    \"\"\"\r\n    Calculate factorial using recursive approach.\r\n    Args:\r\n        n: Non-negative integer\r\n    Returns:\r\n        Factorial of n\r\n    \"\"\"\r\n    if n < 0:\r\n        raise ValueError(\"Factorial is not defined for negative numbers\")\r\n    if n == 0 or n == 1:\r\n        return 1\r\n    return n * factorial_recursive(n - 1)\r\n\r\n# Test the functions\r\ntest_numbers = [0, 1, 5, 10]\r\nfor num in test_numbers:\r\n    iterative_result = factorial_iterative(num)\r\n    recursive_result = factorial_recursive(num)\r\n    print(f\"Factorial of {num}: {iterative_result} (Iterative), {recursive_result} (Recursive)\")', 'EXAMPLE_CODE_1.txt', '2026-06-22 07:07:08', 'D', 60.00),
(17, 19, 22, 'def compute_factorial_iterative(n):\r\n    \"\"\"\r\n    Compute factorial using iterative method.\r\n    Parameters:\r\n        n: Integer (must be non-negative)\r\n    Returns:\r\n        Factorial value\r\n    \"\"\"\r\n    if n < 0:\r\n        raise Exception(\"Negative numbers not allowed for factorial\")\r\n    if n == 0:\r\n        return 1\r\n    \r\n    total = 1\r\n    for i in range(2, n + 1):\r\n        total = total * i\r\n    return total\r\n\r\ndef compute_factorial_recursive(n):\r\n    \"\"\"\r\n    Compute factorial using recursive method.\r\n    Parameters:\r\n        n: Integer (must be non-negative)\r\n    Returns:\r\n        Factorial value\r\n    \"\"\"\r\n    if n < 0:\r\n        raise Exception(\"Negative numbers not allowed for factorial\")\r\n    if n == 0 or n == 1:\r\n        return 1\r\n    return n * compute_factorial_recursive(n - 1)\r\n\r\n# Testing the factorial functions\r\nvalues = [0, 1, 5, 10]\r\nfor value in values:\r\n    iter_result = compute_factorial_iterative(value)\r\n    recur_result = compute_factorial_recursive(value)\r\n    print(f\"{value}! = {iter_result} (iterative) | {recur_result} (recursive)\")', 'EXAMPLE_CODE_2.txt', '2026-06-22 07:07:29', 'D', 60.00),
(18, 19, 23, 'def reverse_string_using_stack(text):\r\n    \"\"\"\r\n    Reverse a string using stack data structure.\r\n    Args:\r\n        text: Input string to reverse\r\n    Returns:\r\n        Reversed string\r\n    \"\"\"\r\n    if not isinstance(text, str):\r\n        raise TypeError(\"Input must be a string\")\r\n    \r\n    stack = []\r\n    # Push all characters onto stack\r\n    for char in text:\r\n        stack.append(char)\r\n    \r\n    # Pop characters to reverse\r\n    reversed_text = \"\"\r\n    while stack:\r\n        reversed_text += stack.pop()\r\n    \r\n    return reversed_text\r\n\r\ndef reverse_string_using_slicing(text):\r\n    \"\"\"\r\n    Reverse a string using Python slicing.\r\n    Args:\r\n        text: Input string to reverse\r\n    Returns:\r\n        Reversed string\r\n    \"\"\"\r\n    if not isinstance(text, str):\r\n        raise TypeError(\"Input must be a string\")\r\n    \r\n    return text[::-1]\r\n\r\ndef is_palindrome(text):\r\n    \"\"\"\r\n    Check if a string is a palindrome (reads same forwards and backwards).\r\n    Args:\r\n        text: Input string to check\r\n    Returns:\r\n        True if palindrome, False otherwise\r\n    \"\"\"\r\n    cleaned_text = text.lower().replace(\" \", \"\")\r\n    return cleaned_text == cleaned_text[::-1]\r\n\r\n# Test functions\r\ntest_strings = [\"hello\", \"racecar\", \"Python\", \"A man a plan a canal Panama\"]\r\nfor s in test_strings:\r\n    reversed_stack = reverse_string_using_stack(s)\r\n    reversed_slice = reverse_string_using_slicing(s)\r\n    palindrome_status = is_palindrome(s)\r\n    print(f\"Original: \'{s}\'\")\r\n    print(f\"Reversed (Stack): \'{reversed_stack}\'\")\r\n    print(f\"Reversed (Slicing): \'{reversed_slice}\'\")\r\n    print(f\"Palindrome: {palindrome_status}\")\r\n    print(\"-\" * 30)', 'EXAMPLE_CODE_4.txt', '2026-06-22 07:07:51', 'A', 90.00),
(19, 21, 25, 'import React, { useState, useEffect } from \'react\';\r\nimport \'./TodoApp.css\';\r\n\r\nconst TodoApp = () => {\r\n  const [todos, setTodos] = useState([]);\r\n  const [inputValue, setInputValue] = useState(\'\');\r\n  const [filter, setFilter] = useState(\'all\');\r\n\r\n  useEffect(() => {\r\n    const storedTodos = localStorage.getItem(\'todos\');\r\n    if (storedTodos) {\r\n      setTodos(JSON.parse(storedTodos));\r\n    }\r\n  }, []);\r\n\r\n  useEffect(() => {\r\n    localStorage.setItem(\'todos\', JSON.stringify(todos));\r\n  }, [todos]);\r\n\r\n  const addTodo = () => {\r\n    if (inputValue.trim() !== \'\') {\r\n      const newTodo = {\r\n        id: Date.now(),\r\n        text: inputValue.trim(),\r\n        completed: false,\r\n        createdAt: new Date().toISOString()\r\n      };\r\n      setTodos([...todos, newTodo]);\r\n      setInputValue(\'\');\r\n    }\r\n  };\r\n\r\n  const toggleTodo = (id) => {\r\n    setTodos(todos.map(todo =>\r\n      todo.id === id ? { ...todo, completed: !todo.completed } : todo\r\n    ));\r\n  };\r\n\r\n  const deleteTodo = (id) => {\r\n    setTodos(todos.filter(todo => todo.id !== id));\r\n  };\r\n\r\n  const getFilteredTodos = () => {\r\n    switch(filter) {\r\n      case \'active\':\r\n        return todos.filter(todo => !todo.completed);\r\n      case \'completed\':\r\n        return todos.filter(todo => todo.completed);\r\n      default:\r\n        return todos;\r\n    }\r\n  };\r\n\r\n  const getPendingCount = () => {\r\n    return todos.filter(todo => !todo.completed).length;\r\n  };\r\n\r\n  return (\r\n    <div className=\"todo-app\">\r\n      <h1>Todo List</h1>\r\n      \r\n      <div className=\"input-section\">\r\n        <input\r\n          type=\"text\"\r\n          value={inputValue}\r\n          onChange={(e) => setInputValue(e.target.value)}\r\n          onKeyPress={(e) => e.key === \'Enter\' && addTodo()}\r\n          placeholder=\"Add a new task...\"\r\n        />\r\n        <button onClick={addTodo}>Add</button>\r\n      </div>\r\n\r\n      <div className=\"filter-section\">\r\n        <button \r\n          className={filter === \'all\' ? \'active\' : \'\'}\r\n          onClick={() => setFilter(\'all\')}\r\n        >\r\n          All\r\n        </button>\r\n        <button \r\n          className={filter === \'active\' ? \'active\' : \'\'}\r\n          onClick={() => setFilter(\'active\')}\r\n        >\r\n          Active\r\n        </button>\r\n        <button \r\n          className={filter === \'completed\' ? \'active\' : \'\'}\r\n          onClick={() => setFilter(\'completed\')}\r\n        >\r\n          Completed\r\n        </button>\r\n      </div>\r\n\r\n      <ul className=\"todo-list\">\r\n        {getFilteredTodos().map(todo => (\r\n          <li key={todo.id} className={todo.completed ? \'completed\' : \'\'}>\r\n            <span onClick={() => toggleTodo(todo.id)}>\r\n              {todo.text}\r\n            </span>\r\n            <button onClick={() => deleteTodo(todo.id)}>Delete</button>\r\n          </li>\r\n        ))}\r\n      </ul>\r\n\r\n      <div className=\"stats\">\r\n        <span>{getPendingCount()} pending tasks</span>\r\n      </div>\r\n    </div>\r\n  );\r\n};\r\n\r\nexport default TodoApp;', 'EXAMPLE_CODE_1.txt', '2026-06-22 14:47:05', 'D', 60.00),
(20, 21, 26, 'import React, { useState, useEffect } from \'react\';\r\nimport \'./TodoApp.css\';\r\n\r\nconst TodoApp = () => {\r\n  const [todos, setTodos] = useState([]);\r\n  const [input, setInput] = useState(\'\');\r\n  const [currentFilter, setCurrentFilter] = useState(\'all\');\r\n\r\n  useEffect(() => {\r\n    const stored = localStorage.getItem(\'todos\');\r\n    if (stored) {\r\n      setTodos(JSON.parse(stored));\r\n    }\r\n  }, []);\r\n\r\n  useEffect(() => {\r\n    localStorage.setItem(\'todos\', JSON.stringify(todos));\r\n  }, [todos]);\r\n\r\n  const addNewTodo = () => {\r\n    if (input.trim() !== \'\') {\r\n      const todo = {\r\n        id: Date.now(),\r\n        text: input.trim(),\r\n        completed: false,\r\n        timestamp: new Date().toISOString()\r\n      };\r\n      setTodos([...todos, todo]);\r\n      setInput(\'\');\r\n    }\r\n  };\r\n\r\n  const toggleComplete = (id) => {\r\n    setTodos(todos.map(todo =>\r\n      todo.id === id ? { ...todo, completed: !todo.completed } : todo\r\n    ));\r\n  };\r\n\r\n  const removeTodo = (id) => {\r\n    setTodos(todos.filter(todo => todo.id !== id));\r\n  };\r\n\r\n  const getFilteredList = () => {\r\n    if (currentFilter === \'active\') {\r\n      return todos.filter(todo => !todo.completed);\r\n    } else if (currentFilter === \'completed\') {\r\n      return todos.filter(todo => todo.completed);\r\n    }\r\n    return todos;\r\n  };\r\n\r\n  const getActiveCount = () => {\r\n    return todos.filter(todo => !todo.completed).length;\r\n  };\r\n\r\n  return (\r\n    <div className=\"todo-app\">\r\n      <h1>Todo List</h1>\r\n      \r\n      <div className=\"input-section\">\r\n        <input\r\n          type=\"text\"\r\n          value={input}\r\n          onChange={(e) => setInput(e.target.value)}\r\n          onKeyDown={(e) => e.key === \'Enter\' && addNewTodo()}\r\n          placeholder=\"Add a new task...\"\r\n        />\r\n        <button onClick={addNewTodo}>Add</button>\r\n      </div>\r\n\r\n      <div className=\"filter-section\">\r\n        <button \r\n          className={currentFilter === \'all\' ? \'active\' : \'\'}\r\n          onClick={() => setCurrentFilter(\'all\')}\r\n        >\r\n          All\r\n        </button>\r\n        <button \r\n          className={currentFilter === \'active\' ? \'active\' : \'\'}\r\n          onClick={() => setCurrentFilter(\'active\')}\r\n        >\r\n          Active\r\n        </button>\r\n        <button \r\n          className={currentFilter === \'completed\' ? \'active\' : \'\'}\r\n          onClick={() => setCurrentFilter(\'completed\')}\r\n        >\r\n          Completed\r\n        </button>\r\n      </div>\r\n\r\n      <ul className=\"todo-list\">\r\n        {getFilteredList().map(todo => (\r\n          <li key={todo.id} className={todo.completed ? \'completed\' : \'\'}>\r\n            <span onClick={() => toggleComplete(todo.id)}>\r\n              {todo.text}\r\n            </span>\r\n            <button onClick={() => removeTodo(todo.id)}>Delete</button>\r\n          </li>\r\n        ))}\r\n      </ul>\r\n\r\n      <div className=\"stats\">\r\n        <span>{getActiveCount()} pending tasks</span>\r\n      </div>\r\n    </div>\r\n  );\r\n};\r\n\r\nexport default TodoApp;', 'EXAMPLE_CODE_2.txt', '2026-06-22 14:47:32', 'D', 60.00),
(21, 21, 36, 'import React, { useState } from \'react\';\r\nimport axios from \'axios\';\r\nimport \'./WeatherApp.css\';\r\n\r\nconst WeatherApp = () => {\r\n  const [city, setCity] = useState(\'\');\r\n  const [weather, setWeather] = useState(null);\r\n  const [loading, setLoading] = useState(false);\r\n  const [error, setError] = useState(null);\r\n\r\n  const fetchWeather = async () => {\r\n    if (!city.trim()) return;\r\n    \r\n    setLoading(true);\r\n    setError(null);\r\n    \r\n    try {\r\n      const API_KEY = process.env.REACT_APP_WEATHER_API_KEY;\r\n      const response = await axios.get(\r\n        `https://api.openweathermap.org/data/2.5/weather`,\r\n        {\r\n          params: {\r\n            q: city,\r\n            appid: API_KEY,\r\n            units: \'metric\'\r\n          }\r\n        }\r\n      );\r\n      setWeather(response.data);\r\n    } catch (err) {\r\n      setError(\'City not found. Please try again.\');\r\n      setWeather(null);\r\n    } finally {\r\n      setLoading(false);\r\n    }\r\n  };\r\n\r\n  const handleKeyPress = (e) => {\r\n    if (e.key === \'Enter\') {\r\n      fetchWeather();\r\n    }\r\n  };\r\n\r\n  const formatDate = (timestamp) => {\r\n    return new Date(timestamp * 1000).toLocaleDateString(\'en-US\', {\r\n      weekday: \'long\',\r\n      year: \'numeric\',\r\n      month: \'long\',\r\n      day: \'numeric\'\r\n    });\r\n  };\r\n\r\n  return (\r\n    <div className=\"weather-app\">\r\n      <h1>Weather App</h1>\r\n      \r\n      <div className=\"search-section\">\r\n        <input\r\n          type=\"text\"\r\n          value={city}\r\n          onChange={(e) => setCity(e.target.value)}\r\n          onKeyPress={handleKeyPress}\r\n          placeholder=\"Enter city name...\"\r\n        />\r\n        <button onClick={fetchWeather}>Search</button>\r\n      </div>\r\n\r\n      {loading && <div className=\"loading\">Loading...</div>}\r\n      \r\n      {error && <div className=\"error\">{error}</div>}\r\n      \r\n      {weather && (\r\n        <div className=\"weather-info\">\r\n          <h2>{weather.name}, {weather.sys.country}</h2>\r\n          <div className=\"weather-details\">\r\n            <div className=\"temperature\">\r\n              <span className=\"temp\">{Math.round(weather.main.temp)}°C</span>\r\n              <span className=\"description\">{weather.weather[0].description}</span>\r\n            </div>\r\n            <div className=\"additional-info\">\r\n              <div>\r\n                <span>Feels like: {Math.round(weather.main.feels_like)}°C</span>\r\n              </div>\r\n              <div>\r\n                <span>Humidity: {weather.main.humidity}%</span>\r\n              </div>\r\n              <div>\r\n                <span>Wind: {weather.wind.speed} m/s</span>\r\n              </div>\r\n              <div>\r\n                <span>Pressure: {weather.main.pressure} hPa</span>\r\n              </div>\r\n            </div>\r\n            <div className=\"date-info\">\r\n              <span>{formatDate(weather.dt)}</span>\r\n            </div>\r\n          </div>\r\n        </div>\r\n      )}\r\n    </div>\r\n  );\r\n};\r\n\r\nexport default WeatherApp;', 'EXAMPLE_CODE_3.txt', '2026-06-22 14:47:59', 'A', 90.00),
(22, 21, 27, 'const express = require(\'express\');\r\nconst mongoose = require(\'mongoose\');\r\nconst bcrypt = require(\'bcryptjs\');\r\nconst jwt = require(\'jsonwebtoken\');\r\nconst cors = require(\'cors\');\r\nrequire(\'dotenv\').config();\r\n\r\nconst app = express();\r\nconst PORT = process.env.PORT || 5000;\r\n\r\n// Middleware\r\napp.use(cors());\r\napp.use(express.json());\r\n\r\n// Database connection\r\nmongoose.connect(process.env.MONGODB_URI, {\r\n  useNewUrlParser: true,\r\n  useUnifiedTopology: true,\r\n})\r\n.then(() => console.log(\'Connected to MongoDB\'))\r\n.catch(err => console.error(\'MongoDB connection error:\', err));\r\n\r\n// User Schema\r\nconst userSchema = new mongoose.Schema({\r\n  username: { type: String, required: true, unique: true, trim: true },\r\n  email: { type: String, required: true, unique: true, lowercase: true },\r\n  password: { type: String, required: true },\r\n  role: { type: String, enum: [\'user\', \'admin\'], default: \'user\' },\r\n  createdAt: { type: Date, default: Date.now },\r\n  updatedAt: { type: Date, default: Date.now }\r\n});\r\n\r\n// Pre-save middleware to hash password\r\nuserSchema.pre(\'save\', async function(next) {\r\n  if (!this.isModified(\'password\')) return next();\r\n  try {\r\n    const salt = await bcrypt.genSalt(10);\r\n    this.password = await bcrypt.hash(this.password, salt);\r\n    next();\r\n  } catch (error) {\r\n    next(error);\r\n  }\r\n});\r\n\r\n// User model\r\nconst User = mongoose.model(\'User\', userSchema);\r\n\r\n// Task Schema\r\nconst taskSchema = new mongoose.Schema({\r\n  title: { type: String, required: true },\r\n  description: { type: String },\r\n  status: { type: String, enum: [\'pending\', \'in-progress\', \'completed\'], default: \'pending\' },\r\n  priority: { type: String, enum: [\'low\', \'medium\', \'high\'], default: \'medium\' },\r\n  userId: { type: mongoose.Schema.Types.ObjectId, ref: \'User\', required: true },\r\n  dueDate: { type: Date },\r\n  createdAt: { type: Date, default: Date.now },\r\n  updatedAt: { type: Date, default: Date.now }\r\n});\r\n\r\n// Pre-save middleware for updatedAt\r\ntaskSchema.pre(\'save\', function(next) {\r\n  this.updatedAt = new Date();\r\n  next();\r\n});\r\n\r\nconst Task = mongoose.model(\'Task\', taskSchema);\r\n\r\n// Authentication middleware\r\nconst authenticateToken = async (req, res, next) => {\r\n  const authHeader = req.headers[\'authorization\'];\r\n  const token = authHeader && authHeader.split(\' \')[1];\r\n  \r\n  if (!token) {\r\n    return res.status(401).json({ error: \'Access token required\' });\r\n  }\r\n\r\n  try {\r\n    const decoded = jwt.verify(token, process.env.JWT_SECRET);\r\n    const user = await User.findById(decoded.userId).select(\'-password\');\r\n    if (!user) {\r\n      return res.status(401).json({ error: \'User not found\' });\r\n    }\r\n    req.user = user;\r\n    next();\r\n  } catch (error) {\r\n    return res.status(403).json({ error: \'Invalid or expired token\' });\r\n  }\r\n};\r\n\r\n// Routes\r\napp.post(\'/api/auth/register\', async (req, res) => {\r\n  try {\r\n    const { username, email, password } = req.body;\r\n    \r\n    // Validation\r\n    if (!username || !email || !password) {\r\n      return res.status(400).json({ error: \'All fields required\' });\r\n    }\r\n    \r\n    if (password.length < 6) {\r\n      return res.status(400).json({ error: \'Password must be at least 6 characters\' });\r\n    }\r\n\r\n    const existingUser = await User.findOne({ \r\n      $or: [{ email }, { username }] \r\n    });\r\n    \r\n    if (existingUser) {\r\n      return res.status(409).json({ \r\n        error: \'Username or email already exists\' \r\n      });\r\n    }\r\n\r\n    const user = new User({ username, email, password });\r\n    await user.save();\r\n\r\n    const token = jwt.sign(\r\n      { userId: user._id },\r\n      process.env.JWT_SECRET,\r\n      { expiresIn: \'7d\' }\r\n    );\r\n\r\n    res.status(201).json({\r\n      token,\r\n      user: {\r\n        id: user._id,\r\n        username: user.username,\r\n        email: user.email,\r\n        role: user.role\r\n      }\r\n    });\r\n  } catch (error) {\r\n    console.error(\'Registration error:\', error);\r\n    res.status(500).json({ error: \'Server error during registration\' });\r\n  }\r\n});\r\n\r\napp.post(\'/api/auth/login\', async (req, res) => {\r\n  try {\r\n    const { email, password } = req.body;\r\n    \r\n    if (!email || !password) {\r\n      return res.status(400).json({ error: \'Email and password required\' });\r\n    }\r\n\r\n    const user = await User.findOne({ email });\r\n    if (!user) {\r\n      return res.status(401).json({ error: \'Invalid credentials\' });\r\n    }\r\n\r\n    const isPasswordValid = await bcrypt.compare(password, user.password);\r\n    if (!isPasswordValid) {\r\n      return res.status(401).json({ error: \'Invalid credentials\' });\r\n    }\r\n\r\n    const token = jwt.sign(\r\n      { userId: user._id },\r\n      process.env.JWT_SECRET,\r\n      { expiresIn: \'7d\' }\r\n    );\r\n\r\n    res.json({\r\n      token,\r\n      user: {\r\n        id: user._id,\r\n        username: user.username,\r\n        email: user.email,\r\n        role: user.role\r\n      }\r\n    });\r\n  } catch (error) {\r\n    console.error(\'Login error:\', error);\r\n    res.status(500).json({ error: \'Server error during login\' });\r\n  }\r\n});\r\n\r\n// Protected routes\r\napp.use(\'/api/tasks\', authenticateToken);\r\n\r\napp.get(\'/api/tasks\', async (req, res) => {\r\n  try {\r\n    const tasks = await Task.find({ userId: req.user._id })\r\n      .sort({ createdAt: -1 });\r\n    res.json(tasks);\r\n  } catch (error) {\r\n    console.error(\'Get tasks error:\', error);\r\n    res.status(500).json({ error: \'Error retrieving tasks\' });\r\n  }\r\n});\r\n\r\napp.post(\'/api/tasks\', async (req, res) => {\r\n  try {\r\n    const { title, description, priority, dueDate } = req.body;\r\n    \r\n    if (!title) {\r\n      return res.status(400).json({ error: \'Title is required\' });\r\n    }\r\n\r\n    const task = new Task({\r\n      title,\r\n      description,\r\n      priority,\r\n      dueDate,\r\n      userId: req.user._id\r\n    });\r\n\r\n    await task.save();\r\n    res.status(201).json(task);\r\n  } catch (error) {\r\n    console.error(\'Create task error:\', error);\r\n    res.status(500).json({ error: \'Error creating task\' });\r\n  }\r\n});\r\n\r\napp.put(\'/api/tasks/:id\', async (req, res) => {\r\n  try {\r\n    const taskId = req.params.id;\r\n    const updates = req.body;\r\n    delete updates._id;\r\n    delete updates.userId;\r\n\r\n    const task = await Task.findOne({ _id: taskId, userId: req.user._id });\r\n    if (!task) {\r\n      return res.status(404).json({ error: \'Task not found\' });\r\n    }\r\n\r\n    Object.assign(task, updates);\r\n    await task.save();\r\n    res.json(task);\r\n  } catch (error) {\r\n    console.error(\'Update task error:\', error);\r\n    res.status(500).json({ error: \'Error updating task\' });\r\n  }\r\n});\r\n\r\napp.delete(\'/api/tasks/:id\', async (req, res) => {\r\n  try {\r\n    const task = await Task.findOneAndDelete({ \r\n      _id: req.params.id, \r\n      userId: req.user._id \r\n    });\r\n    \r\n    if (!task) {\r\n      return res.status(404).json({ error: \'Task not found\' });\r\n    }\r\n    \r\n    res.json({ message: \'Task deleted successfully\' });\r\n  } catch (error) {\r\n    console.error(\'Delete task error:\', error);\r\n    res.status(500).json({ error: \'Error deleting task\' });\r\n  }\r\n});\r\n\r\napp.listen(PORT, () => {\r\n  console.log(`Server running on port ${PORT}`);\r\n});', 'EXAMPLE_CODE_4.txt', '2026-06-22 14:48:24', 'A', 90.00);

-- --------------------------------------------------------

--
-- Table structure for table `course_lecturer`
--

CREATE TABLE `course_lecturer` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `lecturer_id` int(11) NOT NULL,
  `role` enum('penyelaras','pensyarah') DEFAULT 'pensyarah',
  `group_name` varchar(50) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `group_id` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `max_students` int(11) DEFAULT 50
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_lecturer`
--

INSERT INTO `course_lecturer` (`id`, `class_id`, `lecturer_id`, `role`, `group_name`, `is_primary`, `group_id`, `assigned_at`, `max_students`) VALUES
(1, 1, 1, 'penyelaras', NULL, 1, NULL, '2026-01-06 00:00:00', 40),
(2, 1, 2, 'pensyarah', 'GROUP A', 0, NULL, '2026-01-06 00:00:00', 20),
(3, 1, 4, 'pensyarah', 'GROUP B', 0, NULL, '2026-01-06 00:00:00', 20),
(4, 2, 1, 'penyelaras', NULL, 1, NULL, '2026-01-06 00:00:00', 40),
(5, 2, 5, 'pensyarah', 'GROUP A', 0, NULL, '2026-01-06 00:00:00', 20),
(6, 2, 2, 'pensyarah', 'GROUP B', 0, NULL, '2026-01-06 00:00:00', 20),
(7, 3, 3, 'penyelaras', NULL, 1, NULL, '2026-01-06 00:00:00', 40),
(8, 3, 4, 'pensyarah', 'GROUP A', 0, NULL, '2026-01-06 00:00:00', 20),
(9, 3, 5, 'pensyarah', 'GROUP B', 0, NULL, '2026-01-06 00:00:00', 20),
(10, 4, 3, 'penyelaras', NULL, 1, NULL, '2026-01-06 00:00:00', 40),
(11, 4, 2, 'pensyarah', 'GROUP A', 0, NULL, '2026-01-06 00:00:00', 20),
(12, 4, 4, 'pensyarah', 'GROUP B', 0, NULL, '2026-01-06 00:00:00', 20),
(13, 5, 1, 'penyelaras', NULL, 1, NULL, '2026-01-06 00:00:00', 35),
(14, 5, 5, 'pensyarah', 'GROUP A', 0, NULL, '2026-01-06 00:00:00', 18),
(15, 6, 3, 'penyelaras', NULL, 1, NULL, '2026-01-06 00:00:00', 35),
(16, 6, 2, 'pensyarah', 'GROUP A', 0, NULL, '2026-01-06 00:00:00', 18),
(17, 11, 6, 'penyelaras', NULL, 1, NULL, '2026-01-06 00:00:00', 40),
(18, 11, 7, 'pensyarah', 'GROUP A', 0, NULL, '2026-01-06 00:00:00', 20),
(19, 11, 8, 'pensyarah', 'GROUP B', 0, NULL, '2026-01-06 00:00:00', 20),
(20, 12, 7, 'penyelaras', NULL, 1, NULL, '2026-01-06 00:00:00', 40),
(21, 12, 6, 'pensyarah', 'GROUP A', 0, NULL, '2026-01-06 00:00:00', 20),
(22, 12, 9, 'pensyarah', 'GROUP B', 0, NULL, '2026-01-06 00:00:00', 20),
(23, 13, 6, 'penyelaras', NULL, 1, NULL, '2026-01-06 00:00:00', 40),
(24, 13, 10, 'pensyarah', 'GROUP A', 0, NULL, '2026-01-06 00:00:00', 20),
(25, 14, 7, 'penyelaras', NULL, 1, NULL, '2026-01-06 00:00:00', 35),
(26, 14, 9, 'pensyarah', 'GROUP A', 0, NULL, '2026-01-06 00:00:00', 18),
(27, 15, 6, 'penyelaras', NULL, 1, NULL, '2026-01-06 00:00:00', 35),
(28, 15, 10, 'pensyarah', 'GROUP A', 0, NULL, '2026-01-06 00:00:00', 18),
(29, 7, 11, 'penyelaras', NULL, 1, NULL, '2026-06-22 05:22:40', 50),
(30, 7, 12, 'pensyarah', 'GROUP B', 0, NULL, '2026-06-22 08:04:11', 50),
(31, 7, 13, 'pensyarah', 'GROUP C', 0, NULL, '2026-06-22 08:04:41', 50),
(32, 7, 14, 'pensyarah', 'GROUP D', 0, NULL, '2026-06-22 08:05:01', 50);

-- --------------------------------------------------------

--
-- Table structure for table `enrollment`
--

CREATE TABLE `enrollment` (
  `enrollment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `lecturer_id` int(11) DEFAULT 1,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','dropped','completed') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollment`
--

INSERT INTO `enrollment` (`enrollment_id`, `student_id`, `class_id`, `lecturer_id`, `enrolled_at`, `status`) VALUES
(1, 1, 1, 2, '2026-01-10 01:00:00', 'active'),
(2, 2, 1, 2, '2026-01-10 01:00:00', 'active'),
(3, 3, 1, 2, '2026-01-10 01:00:00', 'active'),
(4, 4, 1, 2, '2026-01-10 01:00:00', 'active'),
(5, 5, 1, 2, '2026-01-10 01:00:00', 'active'),
(6, 6, 1, 4, '2026-01-10 01:00:00', 'active'),
(7, 7, 1, 4, '2026-01-10 01:00:00', 'active'),
(8, 8, 1, 4, '2026-01-10 01:00:00', 'active'),
(9, 9, 1, 4, '2026-01-10 01:00:00', 'active'),
(10, 10, 1, 4, '2026-01-10 01:00:00', 'active'),
(11, 1, 2, 5, '2026-01-10 01:00:00', 'active'),
(12, 2, 2, 5, '2026-01-10 01:00:00', 'active'),
(13, 3, 2, 5, '2026-01-10 01:00:00', 'active'),
(14, 4, 2, 5, '2026-01-10 01:00:00', 'active'),
(15, 5, 2, 5, '2026-01-10 01:00:00', 'active'),
(16, 6, 2, 2, '2026-01-10 01:00:00', 'active'),
(17, 7, 2, 2, '2026-01-10 01:00:00', 'active'),
(18, 8, 2, 2, '2026-01-10 01:00:00', 'active'),
(19, 9, 2, 2, '2026-01-10 01:00:00', 'active'),
(20, 10, 2, 2, '2026-01-10 01:00:00', 'active'),
(21, 1, 3, 4, '2026-01-10 01:00:00', 'active'),
(22, 2, 3, 4, '2026-01-10 01:00:00', 'active'),
(23, 3, 3, 4, '2026-01-10 01:00:00', 'active'),
(24, 4, 3, 4, '2026-01-10 01:00:00', 'active'),
(25, 5, 3, 4, '2026-01-10 01:00:00', 'active'),
(26, 6, 3, 5, '2026-01-10 01:00:00', 'active'),
(27, 7, 3, 5, '2026-01-10 01:00:00', 'active'),
(28, 8, 3, 5, '2026-01-10 01:00:00', 'active'),
(29, 9, 3, 5, '2026-01-10 01:00:00', 'active'),
(30, 10, 3, 5, '2026-01-10 01:00:00', 'active'),
(31, 11, 11, 7, '2026-01-10 01:00:00', 'active'),
(32, 12, 11, 7, '2026-01-10 01:00:00', 'active'),
(33, 13, 11, 7, '2026-01-10 01:00:00', 'active'),
(34, 14, 11, 7, '2026-01-10 01:00:00', 'active'),
(35, 15, 11, 7, '2026-01-10 01:00:00', 'active'),
(36, 16, 11, 8, '2026-01-10 01:00:00', 'active'),
(37, 17, 11, 8, '2026-01-10 01:00:00', 'active'),
(38, 18, 11, 8, '2026-01-10 01:00:00', 'active'),
(39, 19, 11, 8, '2026-01-10 01:00:00', 'active'),
(40, 20, 11, 8, '2026-01-10 01:00:00', 'active'),
(41, 11, 12, 6, '2026-01-10 01:00:00', 'active'),
(42, 12, 12, 6, '2026-01-10 01:00:00', 'active'),
(43, 13, 12, 6, '2026-01-10 01:00:00', 'active'),
(44, 14, 12, 6, '2026-01-10 01:00:00', 'active'),
(45, 15, 12, 6, '2026-01-10 01:00:00', 'active'),
(46, 16, 12, 9, '2026-01-10 01:00:00', 'active'),
(47, 17, 12, 9, '2026-01-10 01:00:00', 'active'),
(48, 18, 12, 9, '2026-01-10 01:00:00', 'active'),
(49, 19, 12, 9, '2026-01-10 01:00:00', 'active'),
(50, 20, 12, 9, '2026-01-10 01:00:00', 'active'),
(51, 11, 13, 10, '2026-01-10 01:00:00', 'active'),
(52, 12, 13, 10, '2026-01-10 01:00:00', 'active'),
(53, 13, 13, 10, '2026-01-10 01:00:00', 'active'),
(54, 14, 13, 10, '2026-01-10 01:00:00', 'active'),
(55, 15, 13, 10, '2026-01-10 01:00:00', 'active'),
(56, 16, 13, 10, '2026-01-10 01:00:00', 'active'),
(57, 17, 13, 10, '2026-01-10 01:00:00', 'active'),
(58, 18, 13, 10, '2026-01-10 01:00:00', 'active'),
(59, 19, 13, 10, '2026-01-10 01:00:00', 'active'),
(60, 20, 13, 10, '2026-01-10 01:00:00', 'active'),
(61, 21, 7, 11, '2026-06-22 05:23:33', 'active'),
(62, 22, 7, 11, '2026-06-22 05:30:24', 'active'),
(63, 23, 7, 11, '2026-06-22 05:30:43', 'active'),
(64, 24, 7, 11, '2026-06-22 05:31:10', 'active'),
(65, 25, 7, 12, '2026-06-22 08:26:06', 'active'),
(66, 26, 7, 12, '2026-06-22 08:26:31', 'active'),
(67, 36, 7, 12, '2026-06-22 08:28:34', 'active'),
(68, 27, 7, 12, '2026-06-22 08:28:46', 'active'),
(69, 28, 7, 13, '2026-06-22 08:29:07', 'active'),
(70, 29, 7, 13, '2026-06-22 08:29:20', 'active'),
(71, 30, 7, 13, '2026-06-22 08:29:29', 'active'),
(72, 31, 7, 13, '2026-06-22 08:29:40', 'active'),
(73, 32, 7, 14, '2026-06-22 08:29:55', 'active'),
(74, 33, 7, 14, '2026-06-22 08:31:11', 'active'),
(75, 34, 7, 14, '2026-06-22 08:33:47', 'active'),
(76, 35, 7, 14, '2026-06-22 08:33:58', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `essay_submission`
--

CREATE TABLE `essay_submission` (
  `id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `essay` text NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `final_grade` varchar(2) DEFAULT NULL,
  `total_score` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `essay_submission`
--

INSERT INTO `essay_submission` (`id`, `assignment_id`, `student_id`, `essay`, `file_name`, `submitted_at`, `final_grade`, `total_score`) VALUES
(1, 1, 1, 'An algorithm is a step-by-step procedure or formula for solving a problem. It has five key characteristics: finiteness (must terminate), definiteness (each step is precisely defined), input (zero or more inputs accepted), output (one or more results produced), and effectiveness (steps must be simple enough to be carried out). Real-life example one is a recipe for cooking fried rice: the steps are clearly defined and must be followed in sequence. Real-life example two is the process of finding a book in a library using the Dewey Decimal System. Algorithms form the backbone of all computer programs and help us solve complex problems efficiently.', 'algo_essay_ahmad.txt', '2026-02-06 06:30:00', 'A', 92.00),
(2, 1, 2, 'An algorithm can be defined as a well-defined computational procedure that takes some value as input and produces some value as output. The main characteristics include: it must be finite and terminate after a fixed number of steps; each step must be clearly and precisely stated; it should be effective meaning each step can be performed by a person using pencil and paper in a finite amount of time. A common real-life example is the process we use to sort a deck of playing cards into order, or the way a GPS calculates the shortest route from one point to another using Dijkstra algorithm.', 'algo_essay_nurul.txt', '2026-02-07 02:15:00', 'B', 82.00),
(3, 1, 3, 'Algorithms are foundational to computer science and programming. An algorithm is a finite sequence of well-defined instructions that solves a problem. Key properties are finiteness, definiteness, input, output, and effectiveness. First example: making a cup of tea - you boil water, add tea bag, wait, add milk and sugar. These steps are sequential and finite. Second example: searching for a contact in your phone - the phone uses a binary search algorithm to quickly find the correct entry among thousands. Without algorithms, computers could not function.', 'algo_essay_haziq.txt', '2026-02-07 08:45:00', 'B', 78.00),
(4, 1, 4, 'In computer science, an algorithm refers to a set of step-by-step instructions to solve a particular problem or complete a task. It must be unambiguous, have defined inputs and outputs, be finite, and be feasible. Example one: the process of making an ATM transaction follows a strict algorithm - insert card, enter PIN, select transaction type, enter amount, receive cash, collect receipt. Example two: Google Maps uses complex pathfinding algorithms such as A-star to calculate the optimal route between two locations, considering traffic conditions in real time.', 'algo_essay_siti.txt', '2026-02-07 12:00:00', 'A', 88.00),
(5, 1, 5, 'An algorithm is a precise set of instructions for solving a problem. Characteristics include: must terminate, each step must be exactly defined, must have inputs and outputs, and all steps must be basic enough to be carried out exactly. Real life example one is following a recipe for nasi lemak - each step must be done in order and the process will terminate when the dish is ready. Example two is the process banks use to check if a credit card transaction is fraudulent - they use decision algorithms based on spending patterns and location data.', 'algo_essay_daniel.txt', '2026-02-08 01:00:00', 'B', 80.00),
(6, 1, 6, 'Algorithm is a step by step method of solving a problem. It has to have a start and end. Each step must be clear. Inputs go in and outputs come out. Example one is cooking. You follow steps to make food. Example two is finding shortest path like waze uses algorithm to find best route. I think algorithms are very important in computing because without them we cannot write programs or solve problems systematically.', 'algo_essay_amirah.txt', '2026-02-08 03:30:00', 'C', 65.00),
(7, 1, 7, 'An algorithm is a step-by-step set of instructions designed to perform a specific task or solve a problem. The five properties of a good algorithm are finiteness, definiteness, input, output, and effectiveness. Real-life algorithms are everywhere. When you log into an email account, an authentication algorithm checks your credentials. When you use Spotify, a recommendation algorithm analyzes your listening history to suggest new songs. Algorithms make modern technology possible and efficient.', 'algo_essay_hafiz.txt', '2026-02-07 14:00:00', 'A', 90.00),
(8, 1, 8, 'Algorithms are instructions given to solve a problem. They need to be finite meaning they stop at some point. They also need to be definite so each step is clear. We need input and we get output. An example from everyday life is a washing machine cycle which follows a fixed sequence of fill, wash, rinse, spin. Another example is how search engines like Google rank websites using the PageRank algorithm which counts links to determine importance of pages.', 'algo_essay_liyana.txt', '2026-02-08 00:00:00', 'B', 75.00),
(9, 1, 9, 'Algorithm definition: a finite sequence of well-defined instructions to solve a computational problem. Properties: finite (terminates), definite (unambiguous), input (may have zero or more inputs), output (produces at least one output), effective (each step is basic). Example 1: an automated teller machine follows a programmed algorithm for every transaction. Example 2: sorting algorithms like bubble sort or merge sort arrange data in ascending or descending order. Algorithms are the building blocks of software development.', 'algo_essay_syafiq.txt', '2026-02-07 05:00:00', 'A', 91.00),
(10, 1, 10, 'Algorithms play a crucial role in everyday computing. An algorithm is a set of unambiguous instructions designed to solve a problem in a finite number of steps. Properties include finiteness, definiteness, effectiveness, and valid input and output. Example one: when you use a navigation app, it uses Dijkstra shortest path algorithm to find the best route. Example two: email spam filters use classification algorithms that have been trained to recognize spam patterns in email content. These examples show how pervasive algorithms are in modern life.', 'algo_essay_farhana.txt', '2026-02-08 02:00:00', 'A', 93.00),
(11, 4, 1, 'Learning HTML and CSS has been an exciting journey. HTML provides the structure of a webpage using elements like headings, paragraphs, links, and images. I have used tags such as h1, p, a, img, div, and nav extensively. CSS allows us to style those elements by controlling colors, fonts, layouts, and spacing. I faced challenges with understanding the CSS box model and how margin, padding, and border interact. I also struggled initially with CSS selectors but now understand class, id, and element selectors well. Overall, the foundation I have built will be useful for learning JavaScript next.', 'html_css_ahmad.txt', '2026-02-15 06:00:00', 'A', 90.00),
(12, 4, 2, 'HTML stands for HyperText Markup Language and is the standard language for creating webpages. I learned how to use headings from h1 to h6, create ordered and unordered lists, add hyperlinks and images, and structure pages using div and section elements. CSS or Cascading Style Sheets allows me to control the appearance of HTML elements. The most challenging part for me was understanding CSS specificity and why some styles were overriding others. After practice, I now understand that inline styles override internal styles which override external stylesheets.', 'html_css_nurul.txt', '2026-02-16 02:00:00', 'B', 83.00),
(13, 4, 3, 'HTML and CSS are the two fundamental technologies for building web interfaces. HTML creates the skeleton and CSS adds the skin. I used elements like header, main, footer, article, and aside which are semantic HTML5 elements that improve accessibility and SEO. In CSS, I learned about flexbox which made creating responsive layouts much easier. The biggest challenge was making my site look the same across different browsers. I also learned to use the browser developer tools to debug CSS issues by inspecting elements.', 'html_css_haziq.txt', '2026-02-16 08:00:00', 'B', 85.00),
(14, 4, 4, 'My experience with HTML and CSS has been very valuable. HTML provides semantic structure to web content and I have used tags like nav, article, section, aside, and footer. CSS gave me control over visual presentation. I enjoyed working with the CSS Grid layout system which allowed me to create complex multi-column layouts easily. I faced difficulty with CSS positioning especially when using absolute and relative positioning together. However, after watching tutorials and practicing, I now understand how the stacking context works.', 'html_css_siti.txt', '2026-02-15 12:00:00', 'A', 88.00),
(15, 4, 5, 'HTML and CSS are the building blocks of web development. HTML marks up content with tags and CSS makes it look good. I have learned basic and intermediate HTML including tables, forms, and semantic elements. For CSS, I practiced color schemes, typography, margins, padding, and simple animations. One challenge I faced was understanding the difference between block and inline elements and when to use flexbox versus float for layout. I also find it tricky to center elements vertically but I have found that flexbox makes this easy.', 'html_css_daniel.txt', '2026-02-17 01:00:00', 'B', 79.00),
(16, 4, 6, 'I have learned HTML and CSS this semester. HTML uses tags to structure content and CSS styles it. I used div, p, h1, h2, span, a, img tags. For CSS I learned about colors, fonts, margins and padding. The challenge I faced was making the website look good on mobile. I tried using percentage widths instead of fixed pixels which helped. CSS is powerful but sometimes confusing because small mistakes can break the whole layout of the page.', 'html_css_amirah.txt', '2026-02-17 03:00:00', 'C', 67.00),
(17, 4, 7, 'Studying HTML and CSS opened my eyes to the world of web design. HTML5 introduced semantic tags that give meaning to page structure including header, nav, main, section, article, and footer. I used all of these in my practice projects. CSS transitions and animations were my favorite topics because they add life to static web pages. I created hover effects, fade-in animations, and button animations using pure CSS. The most challenging concept was z-index and layering because it depends on the stacking context which is not always obvious.', 'html_css_hafiz.txt', '2026-02-14 10:00:00', 'A', 94.00),
(18, 4, 8, 'My HTML and CSS learning experience has been progressive. I started with basic tags like html, head, body, h1, and p. Later I learned about forms, tables, and multimedia elements like video and audio. With CSS, I learned about the cascade meaning how styles are applied in priority order based on specificity and inheritance. I found flexbox challenging at first but now I can use it to create flexible and responsive navigation bars and card layouts. Looking forward to combining HTML and CSS with JavaScript for more interactive websites.', 'html_css_liyana.txt', '2026-02-17 00:00:00', 'B', 81.00),
(19, 4, 9, 'HTML and CSS form the foundation of every website. HTML is like the blueprint of a house providing structure, and CSS is the interior design making it look beautiful. I applied semantic HTML tags for better code readability and accessibility. For CSS, I focused on mastering the box model, flexbox, and CSS variables for consistent theming. The challenge I encountered was browser compatibility since some CSS features are not supported in older browsers. I learned to use vendor prefixes and fallback values to handle this.', 'html_css_syafiq.txt', '2026-02-15 14:00:00', 'A', 89.00),
(20, 4, 10, 'Learning HTML and CSS was both challenging and rewarding. I used HTML elements such as semantic tags, form controls, tables, and multimedia. With CSS, I styled elements using selectors, the box model, colors, and typography. The most valuable thing I learned was CSS flexbox because it solved most of my layout problems elegantly. A challenge was understanding CSS specificity and knowing which rule applies when multiple rules target the same element. After learning the specificity calculation, this became clearer and I can now debug CSS conflicts more effectively.', 'html_css_farhana.txt', '2026-02-16 12:00:00', 'A', 87.00),
(21, 9, 11, 'Flat-file systems store data in plain text files, typically one record per line with fields separated by delimiters like commas or tabs. While simple, they suffer from data redundancy, inconsistency, and difficulty querying related data. Relational databases, on the other hand, store data in structured tables with defined relationships between them. They use SQL for querying and support ACID transactions ensuring data integrity. Modern applications prefer relational databases because they eliminate redundancy through normalization, support complex queries through JOIN operations, and provide multi-user access with proper concurrency control.', 'db_intro_farah.txt', '2026-02-13 06:00:00', 'A', 91.00),
(22, 9, 12, 'A flat-file database stores all data in a single table or plain text file. This leads to problems like data redundancy where the same information is repeated, and update anomalies where changing one piece of data requires updating it in multiple places. Relational databases overcome these issues by organizing data into multiple related tables. Each table represents an entity and relationships between entities are expressed using foreign keys. The advantages include data integrity through constraints, efficient storage through normalization, and powerful querying capability through SQL joins and aggregate functions.', 'db_intro_mohd.txt', '2026-02-14 02:00:00', 'A', 88.00),
(23, 9, 13, 'Flat-file systems and relational databases are two different approaches to data storage. Flat files store data as simple text with one entry per line, making them easy to create but difficult to maintain for large datasets. Problems include data duplication and no support for relationships. Relational databases use tables, keys, and relationships to organize data efficiently. The relational model allows for data normalization, reducing redundancy. SQL provides a powerful standard language for data manipulation. Most enterprise applications use relational databases like MySQL, PostgreSQL, or Oracle for these reasons.', 'db_intro_aina.txt', '2026-02-14 08:00:00', 'B', 84.00),
(24, 9, 14, 'The main differences between flat-file systems and relational databases are in structure, scalability, and data integrity. Flat files are simple and work for small datasets but become unmanageable as data grows. They have no way to enforce data types or prevent duplicates. Relational databases use a structured approach with tables and relationships enforced through primary and foreign keys. They support data validation through constraints such as NOT NULL, UNIQUE, and CHECK. Relational databases also support transactions and multi-user environments making them suitable for production applications.', 'db_intro_khairul.txt', '2026-02-13 12:00:00', 'B', 80.00),
(25, 9, 15, 'Flat file systems store data in a simple text format where each record is on one line. They are easy to read with a text editor but difficult to work with programmatically for complex queries. Relational databases organize data into tables connected through keys. The relational model was proposed by E. F. Codd and has been the standard for database design since the 1970s. Modern applications use relational databases because they provide data consistency, reduce storage requirements through normalization, and allow complex queries through SQL. Popular RDBMS include MySQL, PostgreSQL, and Microsoft SQL Server.', 'db_intro_noraina.txt', '2026-02-15 01:00:00', 'B', 78.00),
(26, 9, 16, 'Flat file database keeps all data in one place. It is simple but has problems like data being repeated many times. For example if student address changes you must update it everywhere. Relational database solves this by having separate tables. Each entity has its own table and they are connected with keys. This is better because data is stored once and can be updated easily. SQL is used to query relational databases and it is very powerful. I think relational databases are better for most applications because of these advantages.', 'db_intro_hazwan.txt', '2026-02-15 06:30:00', 'C', 66.00),
(27, 9, 17, 'Flat-file systems store entire datasets in a single file without relationships or constraints. They are suitable for simple use cases like logging or configuration files. However, for complex business applications, relational databases are preferred. Relational databases implement the relational model using tables, primary keys, foreign keys, and indexes. They enforce referential integrity ensuring that data relationships remain consistent. For example, you cannot add an order record without a corresponding customer record. This kind of constraint is not possible in flat-file systems and makes relational databases much safer for critical business data.', 'db_intro_nadia.txt', '2026-02-14 10:00:00', 'B', 82.00),
(28, 9, 18, 'The difference between flat-file systems and relational databases is significant. Flat files are unstructured and cannot enforce relationships between different types of data. This leads to issues like insertion anomalies where adding data causes unintended redundancy, deletion anomalies where deleting one piece of data removes unrelated information, and update anomalies where changes must be made in multiple places. Relational databases address all these issues through normalization and constraints. They also provide indexing for faster searches and views for simplified data access, making them the industry standard for data management.', 'db_intro_irfan.txt', '2026-02-13 14:00:00', 'A', 90.00),
(29, 9, 19, 'Flat-file databases were the earliest form of data storage, storing records in plain text files. While easy to implement, they lack structure and become inefficient with large amounts of data. Searching requires scanning the entire file. Relational databases use the SQL standard to query data efficiently using indexes. The biggest advantage of relational databases is that they model real-world relationships accurately using foreign keys and join operations. For example, a school database can relate students to courses and courses to lecturers in a way that is impossible to achieve cleanly with flat files.', 'db_intro_sarina.txt', '2026-02-15 03:00:00', 'B', 83.00),
(30, 9, 20, 'Flat files are simple and portable but lack the organizational structure needed for complex applications. They cannot enforce data types, prevent duplicates, or model relationships. Relational databases solve all these problems. They use a schema to define the structure of each table including column names, data types, and constraints. ACID properties (Atomicity, Consistency, Isolation, Durability) ensure that transactions are processed reliably. This is critical for banking, healthcare, and e-commerce applications where data accuracy is essential. Without these properties, system failures could leave data in an inconsistent state.', 'db_intro_azrul.txt', '2026-02-14 13:00:00', 'A', 92.00),
(31, 11, 11, 'Database normalization is the process of organizing data to reduce redundancy and improve data integrity. First Normal Form (1NF) requires that each column contains atomic (indivisible) values and each row is unique. Second Normal Form (2NF) requires that the table is in 1NF and every non-key attribute is fully dependent on the entire primary key, eliminating partial dependencies. Third Normal Form (3NF) requires that the table is in 2NF and there are no transitive dependencies, meaning non-key attributes must not depend on other non-key attributes. Normalization is important because it prevents data anomalies and makes updates simpler.', 'norm_farah.txt', '2026-02-18 06:00:00', 'A', 93.00),
(32, 11, 12, 'Normalization is the process of structuring a relational database to reduce data redundancy. 1NF: all values in a column must be of the same kind and each row must be unique. Example: a column with multiple phone numbers violates 1NF. 2NF: no partial dependency on the primary key. Example: if OrderID and ProductID together form the primary key, the product name should not depend only on ProductID. 3NF: no transitive dependency. Example: if StudentID determines DepartmentID and DepartmentID determines DepartmentName, then DepartmentName should be moved to a separate table. Normalization ensures efficient and consistent data storage.', 'norm_mohd.txt', '2026-02-19 02:00:00', 'A', 89.00),
(33, 11, 13, 'Normalization is a technique for organizing database tables to minimize redundancy. The first normal form requires atomic column values and unique rows. For example a table with a hobbies column listing multiple hobbies in one cell violates 1NF. The second normal form eliminates partial dependencies on composite primary keys. The third normal form goes further by eliminating transitive dependencies where one non-key attribute determines another. Normalization is important because it prevents insertion, update, and deletion anomalies that can corrupt your data over time. However over-normalization can sometimes reduce query performance.', 'norm_aina.txt', '2026-02-19 08:00:00', 'B', 85.00),
(34, 11, 14, 'Database normalization organizes data into tables to reduce redundancy. 1NF: each attribute contains only one value per record and all records are unique. 2NF: the table is in 1NF and every attribute that is not part of a candidate key depends on the whole key. 3NF: the table is in 2NF and no non-prime attribute transitively depends on any candidate key. Example: a table with StudentID, StudentName, CourseID, CourseName, InstructorID, InstructorName is not in 3NF because CourseName depends on CourseID and InstructorName depends on InstructorID, not on the StudentID-CourseID primary key.', 'norm_khairul.txt', '2026-02-18 12:00:00', 'B', 81.00),
(35, 11, 15, 'Normalization is a database design technique that organizes tables to minimize redundancy and dependency. In 1NF all columns must have a single value and rows must be unique. In 2NF there should be no partial dependency meaning all non-key columns depend on the full primary key. In 3NF there should be no transitive dependency where a non-key column depends on another non-key column. Normalization is important to prevent anomalies during insert, update, and delete operations. Without it, changing one record might require changing many other records which increases the chance of inconsistencies.', 'norm_noraina.txt', '2026-02-20 01:00:00', 'B', 77.00),
(36, 13, 11, 'Learning SQL has been one of the most practical experiences in this semester. The SELECT statement allows us to retrieve data from tables, with WHERE clauses for filtering, ORDER BY for sorting, and LIMIT for pagination. The INSERT statement adds new records, UPDATE modifies existing ones, and DELETE removes records. I practiced these on a sample student database. One thing I found very useful was the GROUP BY clause combined with aggregate functions like COUNT, SUM, AVG, MAX, and MIN. For example, counting students per program: SELECT program, COUNT(*) FROM student GROUP BY program. SQL is elegant and powerful.', 'sql_farah.txt', '2026-02-24 06:00:00', 'A', 92.00),
(37, 13, 12, 'SQL is the standard language for interacting with relational databases. I have practiced the four main DML operations: SELECT to query data, INSERT to add records, UPDATE to modify records, and DELETE to remove records. The SELECT statement is the most versatile because it supports filtering with WHERE, sorting with ORDER BY, grouping with GROUP BY, and filtering groups with HAVING. An example I practiced was finding the average score per class: SELECT class_id, AVG(score) FROM grades GROUP BY class_id HAVING AVG(score) > 70. This returned only classes with above average performance.', 'sql_mohd.txt', '2026-02-25 02:00:00', 'A', 90.00),
(38, 13, 13, 'My SQL learning journey has been very hands-on. I started with basic SELECT statements and gradually moved to complex queries with JOINs. The SELECT statement retrieves data from one or more tables. I learned INNER JOIN which returns matching rows, LEFT JOIN which returns all rows from the left table including unmatched ones, and RIGHT JOIN which does the opposite. The most challenging concept was subqueries, where a query is nested inside another query. For example: SELECT full_name FROM student WHERE student_id IN (SELECT student_id FROM enrollment WHERE class_id = 1). This selects students enrolled in class 1.', 'sql_aina.txt', '2026-02-25 08:00:00', 'B', 86.00),
(39, 13, 14, 'SQL stands for Structured Query Language and is used to manage relational databases. I practiced the basic CRUD operations. CREATE TABLE to define table structure, INSERT INTO to add data, SELECT to read data, UPDATE to modify data, and DELETE to remove data. I also learned to use constraints like PRIMARY KEY, FOREIGN KEY, NOT NULL, and UNIQUE to maintain data integrity. A useful practice query was: SELECT s.full_name, c.class_name FROM student s JOIN enrollment e ON s.student_id = e.student_id JOIN class c ON e.class_id = c.class_id. This shows each student with their enrolled classes.', 'sql_khairul.txt', '2026-02-24 12:00:00', 'B', 82.00),
(40, 13, 15, 'SQL is a powerful language for database operations. The SELECT statement is the most used and can include: FROM to specify the table, WHERE to filter rows, GROUP BY to aggregate, HAVING to filter aggregated results, ORDER BY to sort, and LIMIT to restrict rows returned. INSERT INTO adds new records with specified values. UPDATE changes existing records using SET and WHERE. DELETE removes records matching the WHERE condition. The challenge I faced was forgetting the WHERE clause in UPDATE and DELETE which could modify or delete all records in a table. This taught me to always test on a small dataset first.', 'sql_noraina.txt', '2026-02-26 01:00:00', 'B', 79.00),
(41, 15, 1, 'Artificial intelligence is rapidly transforming the landscape of modern education. Schools and universities around the world are adopting AI-powered tools to enhance the learning experience for students. One of the most significant benefits of AI in education is personalized learning. AI systems can analyze individual student performance and adapt teaching materials to match their specific needs and learning pace. This means that students who struggle with certain concepts can receive additional support, while advanced learners can move ahead without waiting for their peers. Another important application is automated grading, where AI systems can evaluate essays and programming assignments with remarkable accuracy. AI tutoring systems are also growing in popularity, providing twenty-four-seven assistance to students. However, challenges include privacy concerns, over-reliance on technology, and the risk of widening the digital divide between well-funded and underfunded institutions.', 'ai_edu_ahmad.txt', '2026-06-10 06:00:00', 'A', 91.00),
(42, 15, 2, 'Artificial intelligence is rapidly changing the landscape of modern education. Schools and universities across the world are adopting AI-powered tools to improve the learning experience for students. One of the most important benefits of AI in education is personalized learning. AI systems can examine individual student performance and adjust teaching materials to match their specific needs and learning speed. This means that students who struggle with certain topics can receive extra help, while advanced learners can progress faster without waiting for their classmates. AI also automates grading, saving teachers valuable time. However, there are concerns about data privacy and the risk that students may become too dependent on AI tools rather than developing critical thinking. The key is to use AI as a supplement to human teaching, not a replacement.', 'ai_edu_nurul.txt', '2026-06-11 02:00:00', 'B', 83.00),
(43, 15, 3, 'The rise of artificial intelligence has brought significant changes to the education sector. From my perspective as a university student, I have witnessed firsthand how AI tools are reshaping the way we learn and study. One area where AI has made a huge difference is in research assistance. Students can now use AI-powered search engines and reference managers to find relevant academic papers, summarize articles, and generate citations automatically. AI has also revolutionized language learning through platforms that use natural language processing to provide instant feedback on pronunciation, grammar, and vocabulary. Furthermore, AI chatbots at universities answer student questions about course registration, scholarship opportunities, and campus services around the clock. Despite these advantages, cost, data privacy, and the preference for human interaction remain significant challenges.', 'ai_edu_haziq.txt', '2026-06-12 08:00:00', 'B', 85.00),
(44, 15, 4, 'Artificial intelligence in education offers tremendous potential but also significant challenges. On the benefits side, adaptive learning systems powered by AI can personalize content delivery for each student, improving engagement and academic outcomes. Automated grading tools reduce the administrative burden on educators and provide faster feedback. Virtual teaching assistants powered by AI can answer student questions instantly. However, the challenges are substantial. First, not all students have equal access to AI-powered tools, creating equity issues. Second, collecting student data for AI personalization raises serious privacy concerns under laws like PDPA in Malaysia. Third, educators need significant training to effectively integrate AI tools into their teaching practices. A balanced approach that uses AI to empower rather than replace human teachers is the ideal way forward.', 'ai_edu_siti.txt', '2026-06-10 12:00:00', 'A', 89.00),
(45, 15, 5, 'AI in education is a growing field. AI can help students learn better through personalized feedback. It can also help teachers by grading work faster and identifying students who are struggling. AI tutoring systems are available all the time unlike human teachers who have set working hours. Challenges include the cost of implementing AI systems and concerns about whether AI will replace teachers one day. I believe AI will not replace teachers because teaching is not just about delivering content but also about mentoring and inspiring students. AI should be seen as a tool that makes teaching more efficient not as a competitor to human educators.', 'ai_edu_daniel.txt', '2026-06-13 01:00:00', 'C', 68.00),
(46, 18, 21, 'The integration of artificial intelligence in education has fundamentally transformed how students learn and how teachers instruct. AI-powered adaptive learning platforms analyze individual student performance in real-time, adjusting content difficulty and pacing to match each learner\'s unique needs. This personalization extends beyond simple content delivery; intelligent tutoring systems can identify knowledge gaps and provide targeted interventions before students fall behind. Furthermore, automated assessment tools reduce teachers\' administrative burden, allowing more time for meaningful student interaction. However, the ethical implications of AI in education demand careful consideration - data privacy concerns, algorithmic bias, and the potential for reduced human connection must be addressed. The future likely holds a hybrid model where AI handles routine tasks while human educators focus on mentorship, creativity cultivation, and socio-emotional development. As this technology evolves, institutions must develop robust frameworks for responsible AI implementation that prioritize student welfare and educational equity.', 'EXAMPLE_ESSAY_1.txt', '2026-06-22 05:30:09', 'A', 90.00),
(47, 18, 22, 'Artificial intelligence has significantly changed the educational landscape, creating new opportunities for both students and teachers. AI-driven learning platforms can track student progress and modify educational content to suit individual learning styles. This adaptive approach ensures that struggling students receive additional support while advanced learners can progress at their own pace. Automated grading systems save educators significant time, allowing them to dedicate more energy to direct student engagement. Despite these benefits, we must carefully consider the ethical challenges, including student data protection, algorithmic fairness, and maintaining meaningful human interaction. The ideal educational model will likely combine AI efficiency with human expertise, where teachers mentor students while AI handles repetitive tasks. Educational institutions need to create comprehensive policies for ethical AI use that safeguard student interests.', 'EXAMPLE_ESSAY_2.txt', '2026-06-22 05:30:32', 'A', 90.00),
(48, 18, 23, 'Artificial intelligence has dramatically transformed the educational landscape, creating unprecedented opportunities for both students and educators. AI-powered learning platforms continuously monitor student performance and dynamically adjust content difficulty to match individual learning requirements. This personalized approach ensures that struggling students receive extra help while advanced learners can continue progressing at their optimal speed. Automated grading solutions significantly reduce teachers\' workload, freeing up time for more meaningful student-teacher interactions. However, we must not overlook the ethical issues surrounding AI in education, including data security, algorithmic discrimination, and the erosion of human connection. The most effective educational model will combine AI\'s efficiency with human expertise, where teachers serve as mentors while AI handles routine administrative functions. Educational institutions must develop comprehensive ethical frameworks for AI implementation that protect student privacy and promote equitable learning opportunities.', 'EXAMPLE_ESSAY_3.txt', '2026-06-22 05:30:50', 'A', 90.00),
(49, 18, 24, 'As a university student navigating the digital age, I\'ve witnessed firsthand how AI tools have revolutionized my learning experience. Research has become significantly more efficient with AI-powered search engines and reference managers that can summarize lengthy articles and automatically generate citations. Language learning applications leverage natural language processing to provide instant feedback on pronunciation and grammar, enabling practice anytime without requiring a human instructor. Career guidance chatbots have become invaluable, providing 24/7 support for course registration questions, scholarship inquiries, and campus service navigation. However, I remain cautious about over-reliance on these tools - the cost of implementation can be prohibitive for smaller institutions, and data privacy concerns persist. Additionally, some students feel uncomfortable discussing personal academic challenges with AI systems rather than human advisors. I believe the future lies in a balanced approach where AI enhances rather than replaces human guidance, serving as a complementary tool that empowers students while preserving meaningful human interaction.', 'EXAMPLE_ESSAY_4.txt', '2026-06-22 05:31:19', 'A', 90.00),
(50, 22, 28, 'Modern JavaScript frameworks have revolutionized the way web applications are developed and maintained. Frameworks such as React, Angular, and Vue.js have introduced component-based architecture, which allows developers to build reusable UI components that encapsulate both logic and presentation. This paradigm shift from traditional MVC (Model-View-Controller) patterns to component-based development has significantly improved code maintainability and scalability in large-scale projects.\r\n\r\nOne of the most significant advantages of modern frameworks is the virtual DOM implementation, pioneered by React. The virtual DOM allows efficient updates by minimizing direct manipulation of the actual DOM, resulting in improved performance for dynamic applications. Instead of re-rendering the entire page when state changes, frameworks intelligently calculate the minimal set of changes needed and apply only those updates.\r\n\r\nState management has also evolved dramatically with frameworks. Libraries like Redux and Vuex provide predictable state containers that make application state easier to track and debug. The concept of unidirectional data flow ensures that data changes are predictable and traceable, reducing bugs caused by unpredictable state mutations. This has been particularly beneficial for complex applications with multiple interacting components.\r\n\r\nServer-side rendering (SSR) and static site generation (SSG) are another area where modern frameworks excel. Next.js for React and Nuxt.js for Vue have made it straightforward to implement SSR, improving SEO performance and initial page load times. These frameworks also support hybrid approaches where some pages are pre-rendered while others are rendered on demand, offering flexibility in performance optimization.\r\n\r\nHowever, modern frameworks do come with challenges. The learning curve can be steep, especially when dealing with build tools, routing, and state management simultaneously. Bundle size can also become an issue, requiring careful code splitting and optimization. Despite these challenges, the ecosystem around these frameworks continues to grow, with extensive tooling, comprehensive documentation, and vibrant community support making them the preferred choice for contemporary web development.', 'EXAMPLE_ESSAY_1.txt', '2026-06-22 14:56:35', 'B', 80.00),
(51, 22, 29, 'Modern JavaScript frameworks have revolutionized web development practices significantly. Frameworks such as React, Angular, and Vue.js introduced component-based architecture, enabling developers to build reusable UI components that combine both logic and presentation. This shift from traditional MVC patterns to component-based development has greatly improved code maintainability and scalability for large projects.\r\n\r\nA key advantage of modern frameworks is the virtual DOM implementation, first introduced by React. The virtual DOM enables efficient updates by reducing direct DOM manipulation, leading to better performance in dynamic applications. Instead of re-rendering the entire page on state changes, frameworks calculate the minimal needed changes and apply only those updates.\r\n\r\nState management has also evolved substantially. Libraries like Redux and Vuex offer predictable state containers making application state easier to track and debug. The unidirectional data flow concept ensures data changes are predictable and traceable, reducing bugs from unpredictable state mutations. This benefits complex applications with multiple interacting components.\r\n\r\nServer-side rendering and static site generation are other areas where frameworks excel. Next.js for React and Nuxt.js for Vue simplify SSR implementation, improving SEO and initial load times. These frameworks support hybrid approaches with some pages pre-rendered and others rendered on demand.\r\n\r\nDespite benefits, modern frameworks present challenges. The learning curve can be significant, especially with build tools, routing, and state management. Bundle size requires careful optimization. However, the growing ecosystem with extensive tooling, documentation, and community support makes them preferred for contemporary web development.', 'EXAMPLE_ESSAY_2.txt', '2026-06-22 14:56:52', 'B', 80.00),
(52, 22, 30, 'Responsive web design has become essential in modern web development, with frameworks playing a crucial role in implementing responsive layouts. CSS frameworks like Bootstrap, Tailwind CSS, and Foundation have simplified the process of creating responsive interfaces by providing pre-built components and utility classes. These frameworks typically use a grid system based on flexbox or CSS Grid, allowing developers to create flexible layouts that adapt to different screen sizes.\r\n\r\nModern JavaScript frameworks like React and Vue.js have embraced component-based architecture, which has transformed web development practices. The virtual DOM concept, pioneered by React, enables efficient updates and improved performance. State management tools such as Redux provide predictable state containers for complex applications.\r\n\r\nServer-side rendering capabilities in frameworks like Next.js and Nuxt.js have improved SEO and initial load times. However, developers must consider the learning curve and bundle size optimization when adopting these technologies. The ecosystem continues to evolve with extensive tooling and community support.\r\n\r\nThe approach to responsive design has evolved with CSS Grid Layout and flexbox becoming standard. CSS Grid offers powerful two-dimensional layout control, while flexbox excels at one-dimensional arrangements. Modern responsive techniques include using relative units like rem and vw/vh, along with the clamp() function for fluid typography. CSS custom properties (variables) enable dynamic theming and easier maintenance of design systems across large applications.', 'EXAMPLE_ESSAY_3.txt', '2026-06-22 14:57:06', 'A', 90.00),
(53, 22, 31, 'Web security remains a critical concern in modern application development, with threats evolving alongside technological advancement. Cross-Site Scripting (XSS) attacks continue to be one of the most prevalent vulnerabilities, occurring when malicious scripts are injected into trusted websites. Modern frameworks have built-in protection against XSS by automatically escaping user input, but developers must remain vigilant with custom components that bypass these safeguards.\r\n\r\nCross-Site Request Forgery (CSRF) attacks represent another significant threat, where unauthorized commands are transmitted from a user that the web application trusts. Protection mechanisms like anti-CSRF tokens, SameSite cookies, and additional verification steps have become standard practices. Modern frameworks typically include CSRF protection, but proper implementation requires understanding the underlying mechanisms.\r\n\r\nAuthentication and authorization have evolved significantly, with JWT (JSON Web Tokens) and OAuth 2.0 becoming industry standards. Proper token management, including secure storage, refresh token rotation, and short-lived access tokens, is essential for maintaining security. Role-based access control (RBAC) provides granular authorization, while implementing the principle of least privilege limits potential damage from compromised accounts.\r\n\r\nSecurity headers such as Content Security Policy (CSP), X-Frame-Options, and Strict-Transport-Security provide additional protection layers. CSP is particularly effective at mitigating XSS attacks by controlling which resources the browser can load. Regular security audits, dependency scanning, and penetration testing should be integrated into the development lifecycle to identify vulnerabilities early. As threats continue to evolve, staying updated with security best practices and following the OWASP Top 10 recommendations remains essential for web developers.', 'EXAMPLE_ESSAY_4.txt', '2026-06-22 14:57:24', 'A', 90.00);

-- --------------------------------------------------------

--
-- Table structure for table `group_lecturer`
--

CREATE TABLE `group_lecturer` (
  `group_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `group_name` varchar(50) NOT NULL,
  `lecturer_id` int(11) NOT NULL,
  `max_students` int(11) DEFAULT 50,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lecturer`
--

CREATE TABLE `lecturer` (
  `lecturer_id` int(11) NOT NULL,
  `staff_id` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_no` varchar(15) DEFAULT NULL,
  `lecturer_type` enum('penyelaras','pensyarah','both') DEFAULT 'pensyarah',
  `division` enum('JABATAN KEJURUTERAAN PERISIAN','JABATAN KEJURUTERAAN DATA GUNAAN','LAIN-LAIN') NOT NULL DEFAULT 'LAIN-LAIN',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lecturer`
--

INSERT INTO `lecturer` (`lecturer_id`, `staff_id`, `password`, `full_name`, `email`, `phone_no`, `lecturer_type`, `division`, `created_at`) VALUES
(1, 'S1001', '238c1060f3783b9df35f844d0426ceb5', 'Dr. Ahmad Bin Razak', 'ahmad.razak@utem.edu.my', '012-3456789', 'both', 'JABATAN KEJURUTERAAN PERISIAN', '2026-01-02 00:00:00'),
(2, 'S1002', '238c1060f3783b9df35f844d0426ceb5', 'Prof. Nor Aishah Binti Mohd', 'nor.aishah@utem.edu.my', '019-2345678', 'pensyarah', 'JABATAN KEJURUTERAAN PERISIAN', '2026-01-02 00:00:00'),
(3, 'S1003', '238c1060f3783b9df35f844d0426ceb5', 'Dr. Mohd Faizal Bin Omar', 'mohd.faizal@utem.edu.my', '016-7890123', 'both', 'JABATAN KEJURUTERAAN PERISIAN', '2026-01-02 00:00:00'),
(4, 'S1004', '238c1060f3783b9df35f844d0426ceb5', 'Dr. Siti Zaharah Binti Ahmad', 'siti.zaharah@utem.edu.my', '013-4567890', 'pensyarah', 'JABATAN KEJURUTERAAN PERISIAN', '2026-01-02 00:00:00'),
(5, 'S1005', '238c1060f3783b9df35f844d0426ceb5', 'En. Azman Bin Abdullah', 'azman.abdullah@utem.edu.my', '014-5678901', 'pensyarah', 'JABATAN KEJURUTERAAN PERISIAN', '2026-01-02 00:00:00'),
(6, 'S1006', '238c1060f3783b9df35f844d0426ceb5', 'Dr. Norashikin Binti Ibrahim', 'norashikin.ibrahim@utem.edu.my', '012-9876543', 'both', 'JABATAN KEJURUTERAAN DATA GUNAAN', '2026-01-02 00:00:00'),
(7, 'S1007', '238c1060f3783b9df35f844d0426ceb5', 'Dr. Mohd Ridzuan Bin Yahya', 'mohd.ridzuan@utem.edu.my', '019-8765432', 'both', 'JABATAN KEJURUTERAAN DATA GUNAAN', '2026-01-02 00:00:00'),
(8, 'S1008', '238c1060f3783b9df35f844d0426ceb5', 'Prof. Wan Norhayati Binti Wan Harun', 'wan.norhayati@utem.edu.my', '016-5432109', 'pensyarah', 'JABATAN KEJURUTERAAN DATA GUNAAN', '2026-01-02 00:00:00'),
(9, 'S1009', '238c1060f3783b9df35f844d0426ceb5', 'Dr. Zuliana Binti Ismail', 'zuliana.ismail@utem.edu.my', '013-6789012', 'both', 'JABATAN KEJURUTERAAN DATA GUNAAN', '2026-01-02 00:00:00'),
(10, 'S1010', '238c1060f3783b9df35f844d0426ceb5', 'En. Khairul Anuar Bin Hamid', 'khairul.anuar@utem.edu.my', '014-7890123', 'both', 'JABATAN KEJURUTERAAN DATA GUNAAN', '2026-01-02 00:00:00'),
(11, 'S1111', '238c1060f3783b9df35f844d0426ceb5', 'TS MASLITA BINTI ABD AZIZ', 'maslita@utem.edu.my', '0124486695', 'both', 'JABATAN KEJURUTERAAN PERISIAN', '2026-06-22 05:22:01'),
(12, 'S1112', '238c1060f3783b9df35f844d0426ceb5', 'DR. ABDUL KARIM BIN MOHAMAD', 'karim@utem.edu.my', '01155263965', 'both', 'JABATAN KEJURUTERAAN PERISIAN', '2026-06-22 07:41:24'),
(13, 'S1113', '238c1060f3783b9df35f844d0426ceb5', 'AMIRUL RAMZANI BIN RADZID', 'amirul@utem.edu.my', '0123699856', 'both', 'JABATAN KEJURUTERAAN PERISIAN', '2026-06-22 07:42:01'),
(14, 'S1114', '238c1060f3783b9df35f844d0426ceb5', 'DR. ASNIYANI NUR HAIDAR BINTI ABDULLAH', 'asniyani@utem.edu.my', '0152689587', 'both', 'JABATAN KEJURUTERAAN PERISIAN', '2026-06-22 07:44:06');

-- --------------------------------------------------------

--
-- Table structure for table `performance_report`
--

CREATE TABLE `performance_report` (
  `report_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `generated_by` int(11) NOT NULL,
  `report_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`report_data`)),
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `performance_report`
--

INSERT INTO `performance_report` (`report_id`, `class_id`, `generated_by`, `report_data`, `generated_at`) VALUES
(1, 1, 2, '{\"class_name\":\"Programming Fundamentals\",\"class_code\":\"BITS1013\",\"total_students\":10,\"submitted\":10,\"not_submitted\":0,\"grade_distribution\":{\"A\":5,\"B\":4,\"C\":1,\"D\":0,\"F\":0},\"average_score\":84.6,\"highest_score\":93.0,\"lowest_score\":65.0,\"assignments_completed\":2,\"report_notes\":\"Overall performance is satisfactory. Most students understand algorithm concepts well. Student B032420006 needs additional support.\"}', '2026-02-25 01:00:00'),
(2, 2, 5, '{\"class_name\":\"Web Development Basics\",\"class_code\":\"BITS1023\",\"total_students\":10,\"submitted\":10,\"not_submitted\":0,\"grade_distribution\":{\"A\":6,\"B\":3,\"C\":1,\"D\":0,\"F\":0},\"average_score\":84.3,\"highest_score\":94.0,\"lowest_score\":67.0,\"assignments_completed\":1,\"report_notes\":\"Students show good understanding of HTML and CSS fundamentals. Flexbox and CSS Grid topics need reinforcement. B032420007 is an outstanding performer.\"}', '2026-02-20 02:00:00'),
(3, 11, 7, '{\"class_name\":\"Database Fundamentals\",\"class_code\":\"BITD1013\",\"total_students\":10,\"submitted\":10,\"not_submitted\":0,\"grade_distribution\":{\"A\":5,\"B\":4,\"C\":1,\"D\":0,\"F\":0},\"average_score\":84.4,\"highest_score\":92.0,\"lowest_score\":66.0,\"assignments_completed\":2,\"report_notes\":\"Strong foundational understanding of relational database concepts. B032420106 requires additional guidance. B032420101 and B032420110 are top performers.\"}', '2026-02-20 03:00:00'),
(4, 12, 6, '{\"class_name\":\"Data Modeling\",\"class_code\":\"BITD1023\",\"total_students\":10,\"submitted\":5,\"not_submitted\":5,\"grade_distribution\":{\"A\":2,\"B\":3,\"C\":0,\"D\":0,\"F\":0},\"average_score\":85.0,\"highest_score\":93.0,\"lowest_score\":77.0,\"assignments_completed\":1,\"report_notes\":\"Only Group A students have submitted so far. Group B submission pending. Normalization concepts are well understood by submitting students.\"}', '2026-02-22 01:00:00'),
(5, 13, 10, '{\"class_name\":\"SQL Programming\",\"class_code\":\"BITD1033\",\"total_students\":10,\"submitted\":10,\"not_submitted\":0,\"grade_distribution\":{\"A\":3,\"B\":3,\"C\":0,\"D\":0,\"F\":0},\"average_score\":84.6,\"highest_score\":94.0,\"lowest_score\":70.0,\"assignments_completed\":2,\"report_notes\":\"Good overall SQL proficiency. B032420104 submitted minimal queries and needs improvement. JOIN concepts should be revised in the next class session.\"}', '2026-03-20 02:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `plagiarism_report`
--

CREATE TABLE `plagiarism_report` (
  `report_id` int(11) NOT NULL,
  `submission_id` int(11) NOT NULL,
  `submission_type` enum('essay','code') NOT NULL,
  `similarity_percentage` float DEFAULT 0,
  `matched_submission_id` int(11) DEFAULT NULL,
  `matched_student_id` int(11) DEFAULT NULL,
  `matched_source_title` varchar(500) DEFAULT NULL,
  `source_type` enum('internal','web','scholar') NOT NULL,
  `matched_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `plagiarism_report`
--

INSERT INTO `plagiarism_report` (`report_id`, `submission_id`, `submission_type`, `similarity_percentage`, `matched_submission_id`, `matched_student_id`, `matched_source_title`, `source_type`, `matched_text`, `created_at`) VALUES
(1, 42, 'essay', 87.5, 41, 1, 'Ahmad Fikri - AI in Education Essay (B032420001)', 'internal', 'AI systems can examine individual student performance and adjust teaching materials to match their specific needs and learning speed. This means that students who struggle with certain topics can receive extra help, while advanced learners can progress faster without waiting for their classmates.', '2026-06-11 04:00:00'),
(2, 43, 'essay', 34.2, NULL, NULL, 'The Role of Artificial Intelligence in Modern Education - ResearchGate 2025', 'scholar', 'AI tools are reshaping the way we learn and study. One area where AI has made a huge difference is in research assistance. Students can now use AI-powered search engines and reference managers to find relevant academic papers, summarize articles, and generate citations automatically.', '2026-06-12 10:00:00'),
(3, 2, 'essay', 62, 1, 1, 'Ahmad Fikri - Introduction to Algorithms (B032420001)', 'internal', 'An algorithm can be defined as a well-defined computational procedure. The main characteristics include: it must be finite and terminate; each step must be clearly and precisely stated.', '2026-02-07 10:00:00'),
(4, 6, 'essay', 22.5, NULL, NULL, 'What is an Algorithm? - GeeksforGeeks', 'web', 'Algorithm is a step-by-step method of solving a problem. It has to have a start and end. Each step must be clear.', '2026-02-08 06:00:00'),
(5, 22, 'essay', 55.3, 21, 11, 'Farah Diana - Introduction to Databases (B032420101)', 'internal', 'Flat-file database stores all data in a single table. This leads to problems like data redundancy. Relational databases overcome these issues by organizing data into multiple related tables.', '2026-02-14 07:00:00'),
(6, 26, 'essay', 28.7, NULL, NULL, 'Flat File vs Relational Database: Key Differences - TechRadar', 'web', 'Flat file database keeps all data in one place. It is simple but has problems like data being repeated many times.', '2026-02-15 09:00:00'),
(7, 6, 'code', 71, 5, 5, 'Daniel Iskandar - Basic Python Calculator (B032420005)', 'internal', 'a = input(\"Enter a: \")\nb = input(\"Enter b: \")\na = int(a)\nb = int(b)\nprint(a+b)', '2026-02-22 05:00:00'),
(8, 14, 'code', 68.5, 13, 13, 'Aina Sabrina - SQL Query Assignment (B032420103)', 'internal', 'SELECT * FROM student;\nSELECT * FROM student WHERE gpa > 3.5;\nSELECT program, count(*) FROM student GROUP BY program;', '2026-03-14 14:00:00'),
(9, 32, 'essay', 79.2, 31, 11, 'Farah Diana - Normalization Essay (B032420101)', 'internal', '1NF: all values in a column must be of the same kind and each row must be unique. 2NF: no partial dependency on the primary key. 3NF: no transitive dependency.', '2026-02-19 05:00:00'),
(10, 12, 'essay', 31.4, NULL, NULL, 'Introduction to HTML and CSS - MDN Web Docs', 'web', 'CSS specificity determines which CSS rule applies when multiple rules target the same element. Inline styles have the highest specificity, followed by ID selectors, class selectors, and element selectors.', '2026-02-16 06:00:00'),
(11, 38, 'essay', 26.8, NULL, NULL, 'SQL Joins Explained - W3Schools', 'web', 'INNER JOIN returns matching rows. LEFT JOIN returns all rows from the left table including unmatched ones. RIGHT JOIN does the opposite.', '2026-02-25 10:00:00'),
(12, 44, 'essay', 19.5, NULL, NULL, 'AI in Education: Opportunities and Challenges - UNESCO 2025 Report', 'scholar', 'Adaptive learning systems powered by AI can personalize content delivery for each student, improving engagement and academic outcomes.', '2026-06-10 16:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `student_id` int(11) NOT NULL,
  `matric_no` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `program` varchar(100) NOT NULL,
  `phone_no` varchar(15) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`student_id`, `matric_no`, `password`, `full_name`, `email`, `program`, `phone_no`, `created_at`) VALUES
(1, 'B032420001', '238c1060f3783b9df35f844d0426ceb5', 'Ahmad Fikri Bin Abdullah', 'ahmad.fikri@student.utem.edu.my', 'BITS', '011-2345678', '2026-01-05 01:00:00'),
(2, 'B032420002', '238c1060f3783b9df35f844d0426ceb5', 'Nurul Aisyah Binti Mohd Zain', 'nurul.aisyah@student.utem.edu.my', 'BITS', '011-3456789', '2026-01-05 01:00:00'),
(3, 'B032420003', '238c1060f3783b9df35f844d0426ceb5', 'Muhammad Haziq Bin Rosli', 'muhammad.haziq@student.utem.edu.my', 'BITS', '011-4567890', '2026-01-05 01:00:00'),
(4, 'B032420004', '238c1060f3783b9df35f844d0426ceb5', 'Siti Nurhaliza Binti Ahmad', 'siti.nurhaliza@student.utem.edu.my', 'BITS', '011-5678901', '2026-01-05 01:00:00'),
(5, 'B032420005', '238c1060f3783b9df35f844d0426ceb5', 'Daniel Bin Iskandar', 'daniel.iskandar@student.utem.edu.my', 'BITS', '011-6789012', '2026-01-05 01:00:00'),
(6, 'B032420006', '238c1060f3783b9df35f844d0426ceb5', 'Amirah Binti Ramli', 'amirah.ramli@student.utem.edu.my', 'BITS', '011-7890123', '2026-01-05 01:00:00'),
(7, 'B032420007', '238c1060f3783b9df35f844d0426ceb5', 'Hafiz Bin Zainal', 'hafiz.zainal@student.utem.edu.my', 'BITS', '011-8901234', '2026-01-05 01:00:00'),
(8, 'B032420008', '238c1060f3783b9df35f844d0426ceb5', 'Liyana Binti Mohd Noor', 'liyana.noor@student.utem.edu.my', 'BITS', '011-9012345', '2026-01-05 01:00:00'),
(9, 'B032420009', '238c1060f3783b9df35f844d0426ceb5', 'Syafiq Bin Hamdan', 'syafiq.hamdan@student.utem.edu.my', 'BITS', '012-0123456', '2026-01-05 01:00:00'),
(10, 'B032420010', '238c1060f3783b9df35f844d0426ceb5', 'Nur Farhana Binti Kamarudin', 'nur.farhana@student.utem.edu.my', 'BITS', '012-1234567', '2026-01-05 01:00:00'),
(11, 'B032420101', '238c1060f3783b9df35f844d0426ceb5', 'Farah Diana Binti Sulaiman', 'farah.diana@student.utem.edu.my', 'BITD', '012-2345678', '2026-01-05 01:00:00'),
(12, 'B032420102', '238c1060f3783b9df35f844d0426ceb5', 'Mohd Faiz Bin Abdul Rahman', 'mohd.faiz@student.utem.edu.my', 'BITD', '012-3456789', '2026-01-05 01:00:00'),
(13, 'B032420103', '238c1060f3783b9df35f844d0426ceb5', 'Aina Sabrina Binti Ibrahim', 'aina.sabrina@student.utem.edu.my', 'BITD', '012-4567890', '2026-01-05 01:00:00'),
(14, 'B032420104', '238c1060f3783b9df35f844d0426ceb5', 'Khairul Anwar Bin Hassan', 'khairul.anwar@student.utem.edu.my', 'BITD', '012-5678901', '2026-01-05 01:00:00'),
(15, 'B032420105', '238c1060f3783b9df35f844d0426ceb5', 'Nor Aina Binti Zulkifli', 'nor.aina@student.utem.edu.my', 'BITD', '012-6789012', '2026-01-05 01:00:00'),
(16, 'B032420106', '238c1060f3783b9df35f844d0426ceb5', 'Hazwan Bin Aziz', 'hazwan.aziz@student.utem.edu.my', 'BITD', '012-7890123', '2026-01-05 01:00:00'),
(17, 'B032420107', '238c1060f3783b9df35f844d0426ceb5', 'Nadia Binti Shaharuddin', 'nadia.shaharuddin@student.utem.edu.my', 'BITD', '012-8901234', '2026-01-05 01:00:00'),
(18, 'B032420108', '238c1060f3783b9df35f844d0426ceb5', 'Irfan Bin Yusof', 'irfan.yusof@student.utem.edu.my', 'BITD', '012-9012345', '2026-01-05 01:00:00'),
(19, 'B032420109', '238c1060f3783b9df35f844d0426ceb5', 'Sarina Binti Othman', 'sarina.othman@student.utem.edu.my', 'BITD', '013-0123456', '2026-01-05 01:00:00'),
(20, 'B032420110', '238c1060f3783b9df35f844d0426ceb5', 'Azrul Bin Mansor', 'azrul.mansor@student.utem.edu.my', 'BITD', '013-1234567', '2026-01-05 01:00:00'),
(21, 'B032420093', '238c1060f3783b9df35f844d0426ceb5', 'Khairin Syahmi', 'khairinsyahmi0208@gmail.com', 'BITS', '0196549842', '2026-06-22 05:18:23'),
(22, 'B032420013', '238c1060f3783b9df35f844d0426ceb5', 'AIN NAJWA BINTI RIZZUAN', 'B032420013@student.utem.edu.my', 'BITS', '0137448895', '2026-06-22 05:19:09'),
(23, 'B032420151', '238c1060f3783b9df35f844d0426ceb5', 'SITI RABIATUL NAJIHA BINTI MOHAMAD FAZILAH', 'B032420151@student.utem.edu.my', 'BITS', '0145544778', '2026-06-22 05:19:45'),
(24, 'B032420781', '238c1060f3783b9df35f844d0426ceb5', 'NUR AJMAL BIN ZAINUDIN', 'B032420781@student.utem.edu.my', 'BITS', '0158847759', '2026-06-22 05:20:38'),
(25, 'B032420011', '238c1060f3783b9df35f844d0426ceb5', 'Nurul Izzati Binti Abdullah', 'nurul.izzati@student.utem.edu.my', 'BITS', '011-7890123', '2026-06-22 07:59:39'),
(26, 'B032420012', '238c1060f3783b9df35f844d0426ceb5', 'Muhammad Aiman Bin Rosli', 'muhammad.aiman@student.utem.edu.my', 'BITS', '011-8901234', '2026-06-22 07:59:39'),
(27, 'B032420014', '238c1060f3783b9df35f844d0426ceb5', 'Ahmad Zahir Bin Hamzah', 'ahmad.zahir@student.utem.edu.my', 'BITS', '012-3456789', '2026-06-22 07:59:39'),
(28, 'B032420015', '238c1060f3783b9df35f844d0426ceb5', 'Nur Syazwani Binti Kamarul', 'nur.syazwani@student.utem.edu.my', 'BITS', '012-4567890', '2026-06-22 07:59:39'),
(29, 'B032420016', '238c1060f3783b9df35f844d0426ceb5', 'Mohd Hafiz Bin Abdullah', 'mohd.hafiz@student.utem.edu.my', 'BITS', '012-5678901', '2026-06-22 07:59:40'),
(30, 'B032420017', '238c1060f3783b9df35f844d0426ceb5', 'Siti Aisyah Binti Mohd Noor', 'siti.aisyah@student.utem.edu.my', 'BITS', '012-6789012', '2026-06-22 07:59:40'),
(31, 'B032420018', '238c1060f3783b9df35f844d0426ceb5', 'Amirul Hakim Bin Zainuddin', 'amirul.hakim@student.utem.edu.my', 'BITS', '012-7890123', '2026-06-22 07:59:40'),
(32, 'B032420019', '238c1060f3783b9df35f844d0426ceb5', 'Nurul Hidayah Binti Arifin', 'nurul.hidayah@student.utem.edu.my', 'BITS', '013-1234567', '2026-06-22 07:59:40'),
(33, 'B032420020', '238c1060f3783b9df35f844d0426ceb5', 'Muhammad Faiz Bin Ismail', 'muhammad.faiz@student.utem.edu.my', 'BITS', '013-2345678', '2026-06-22 07:59:40'),
(34, 'B032420021', '238c1060f3783b9df35f844d0426ceb5', 'Farah Hanim Binti Zulkifli', 'farah.hanim@student.utem.edu.my', 'BITS', '013-3456789', '2026-06-22 07:59:40'),
(35, 'B032420022', '238c1060f3783b9df35f844d0426ceb5', 'Syafiq Bin Mohd Yassin', 'syafiq.yassin@student.utem.edu.my', 'BITS', '013-4567890', '2026-06-22 07:59:40'),
(36, 'B032420023', '238c1060f3783b9df35f844d0426ceb5', 'Muhammad Aiman Azri Bin Affandi', 'B032420023@student.utem.edumy', 'BITS', '01144678763', '2026-06-22 08:28:20');

-- --------------------------------------------------------

--
-- Table structure for table `student_group_history`
--

CREATE TABLE `student_group_history` (
  `history_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `old_lecturer_id` int(11) DEFAULT NULL,
  `new_lecturer_id` int(11) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `changed_by` int(11) DEFAULT NULL COMMENT 'admin or student who changed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_group_history`
--

INSERT INTO `student_group_history` (`history_id`, `student_id`, `class_id`, `old_lecturer_id`, `new_lecturer_id`, `changed_at`, `changed_by`) VALUES
(1, 21, 7, NULL, 11, '2026-06-22 05:23:33', 21),
(2, 22, 7, NULL, 11, '2026-06-22 05:30:24', 22),
(3, 23, 7, NULL, 11, '2026-06-22 05:30:43', 23),
(4, 24, 7, NULL, 11, '2026-06-22 05:31:10', 24),
(5, 25, 7, NULL, 12, '2026-06-22 08:26:06', 25),
(6, 26, 7, NULL, 12, '2026-06-22 08:26:31', 26),
(7, 36, 7, NULL, 12, '2026-06-22 08:28:34', 36),
(8, 27, 7, NULL, 12, '2026-06-22 08:28:46', 27),
(9, 28, 7, NULL, 13, '2026-06-22 08:29:07', 28),
(10, 29, 7, NULL, 13, '2026-06-22 08:29:20', 29),
(11, 30, 7, NULL, 13, '2026-06-22 08:29:29', 30),
(12, 31, 7, NULL, 13, '2026-06-22 08:29:40', 31),
(13, 32, 7, NULL, 14, '2026-06-22 08:29:55', 32),
(14, 33, 7, NULL, 14, '2026-06-22 08:31:11', 33),
(15, 34, 7, NULL, 14, '2026-06-22 08:33:47', 34),
(16, 35, 7, NULL, 14, '2026-06-22 08:33:58', 35);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_assignment_group`
-- (See below for the actual view)
--
CREATE TABLE `v_assignment_group` (
`assignment_id` int(11)
,`tittle` varchar(200)
,`description` text
,`start_date` datetime
,`due_date` datetime
,`type` enum('essay','code')
,`class_id` int(11)
,`class_name` varchar(100)
,`class_code` varchar(20)
,`group_id` int(11)
,`assignment_group_name` varchar(50)
,`lecturer_id` int(11)
,`lecturer_name` varchar(100)
,`staff_id` varchar(20)
,`lecturer_role` enum('penyelaras','pensyarah')
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_course_lecturers`
-- (See below for the actual view)
--
CREATE TABLE `v_course_lecturers` (
`class_id` int(11)
,`class_name` varchar(100)
,`class_code` varchar(20)
,`class_group_name` varchar(50)
,`lecturer_id` int(11)
,`lecturer_name` varchar(100)
,`staff_id` varchar(20)
,`lecturer_role` enum('penyelaras','pensyarah')
,`is_primary` tinyint(1)
,`lecturer_group_id` int(11)
,`student_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_students_by_group`
-- (See below for the actual view)
--
CREATE TABLE `v_students_by_group` (
`class_id` int(11)
,`class_name` varchar(100)
,`class_code` varchar(20)
,`group_name` varchar(50)
,`lecturer_id` int(11)
,`lecturer_name` varchar(100)
,`total_students` bigint(21)
,`student_list` mediumtext
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_student_lecturer`
-- (See below for the actual view)
--
CREATE TABLE `v_student_lecturer` (
`student_id` int(11)
,`matric_no` varchar(20)
,`student_name` varchar(100)
,`program` varchar(100)
,`class_id` int(11)
,`class_name` varchar(100)
,`class_code` varchar(20)
,`group_name` varchar(50)
,`lecturer_id` int(11)
,`lecturer_name` varchar(100)
,`staff_id` varchar(20)
,`lecturer_role` enum('penyelaras','pensyarah')
,`is_primary` tinyint(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_submissions_with_group`
-- (See below for the actual view)
--
CREATE TABLE `v_submissions_with_group` (
`submission_type` varchar(5)
,`submission_id` int(11)
,`assignment_id` int(11)
,`student_id` int(11)
,`final_grade` varchar(2)
,`submitted_at` timestamp
,`class_id` int(11)
,`group_id` int(11)
,`assignment_lecturer_id` int(11)
,`student_lecturer_id` int(11)
);

-- --------------------------------------------------------

--
-- Structure for view `v_assignment_group`
--
DROP TABLE IF EXISTS `v_assignment_group`;

CREATE ALGORITHM=UNDEFINED DEFINER=`` SQL SECURITY DEFINER VIEW `v_assignment_group`  AS SELECT `a`.`assignment_id` AS `assignment_id`, `a`.`tittle` AS `tittle`, `a`.`description` AS `description`, `a`.`start_date` AS `start_date`, `a`.`due_date` AS `due_date`, `a`.`type` AS `type`, `a`.`class_id` AS `class_id`, `c`.`class_name` AS `class_name`, `c`.`class_code` AS `class_code`, `a`.`group_id` AS `group_id`, `c`.`group_name` AS `assignment_group_name`, `a`.`lecturer_id` AS `lecturer_id`, `l`.`full_name` AS `lecturer_name`, `l`.`staff_id` AS `staff_id`, `cl`.`role` AS `lecturer_role` FROM (((`assignment` `a` join `class` `c` on(`a`.`class_id` = `c`.`class_id`)) join `lecturer` `l` on(`a`.`lecturer_id` = `l`.`lecturer_id`)) join `course_lecturer` `cl` on(`a`.`class_id` = `cl`.`class_id` and `l`.`lecturer_id` = `cl`.`lecturer_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_course_lecturers`
--
DROP TABLE IF EXISTS `v_course_lecturers`;

CREATE ALGORITHM=UNDEFINED DEFINER=`` SQL SECURITY DEFINER VIEW `v_course_lecturers`  AS SELECT `c`.`class_id` AS `class_id`, `c`.`class_name` AS `class_name`, `c`.`class_code` AS `class_code`, `c`.`group_name` AS `class_group_name`, `l`.`lecturer_id` AS `lecturer_id`, `l`.`full_name` AS `lecturer_name`, `l`.`staff_id` AS `staff_id`, `cl`.`role` AS `lecturer_role`, `cl`.`is_primary` AS `is_primary`, `cl`.`group_id` AS `lecturer_group_id`, count(distinct `e`.`student_id`) AS `student_count` FROM (((`class` `c` join `course_lecturer` `cl` on(`c`.`class_id` = `cl`.`class_id`)) join `lecturer` `l` on(`cl`.`lecturer_id` = `l`.`lecturer_id`)) left join `enrollment` `e` on(`c`.`class_id` = `e`.`class_id` and `l`.`lecturer_id` = `e`.`lecturer_id`)) GROUP BY `c`.`class_id`, `l`.`lecturer_id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_students_by_group`
--
DROP TABLE IF EXISTS `v_students_by_group`;

CREATE ALGORITHM=UNDEFINED DEFINER=`` SQL SECURITY DEFINER VIEW `v_students_by_group`  AS SELECT `c`.`class_id` AS `class_id`, `c`.`class_name` AS `class_name`, `c`.`class_code` AS `class_code`, `c`.`group_name` AS `group_name`, `e`.`lecturer_id` AS `lecturer_id`, `l`.`full_name` AS `lecturer_name`, count(`e`.`student_id`) AS `total_students`, group_concat(`s`.`matric_no` order by `s`.`matric_no` ASC separator ', ') AS `student_list` FROM (((`class` `c` join `enrollment` `e` on(`c`.`class_id` = `e`.`class_id`)) join `lecturer` `l` on(`e`.`lecturer_id` = `l`.`lecturer_id`)) join `student` `s` on(`e`.`student_id` = `s`.`student_id`)) GROUP BY `c`.`class_id`, `e`.`lecturer_id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_student_lecturer`
--
DROP TABLE IF EXISTS `v_student_lecturer`;

CREATE ALGORITHM=UNDEFINED DEFINER=`` SQL SECURITY DEFINER VIEW `v_student_lecturer`  AS SELECT `s`.`student_id` AS `student_id`, `s`.`matric_no` AS `matric_no`, `s`.`full_name` AS `student_name`, `s`.`program` AS `program`, `e`.`class_id` AS `class_id`, `c`.`class_name` AS `class_name`, `c`.`class_code` AS `class_code`, `c`.`group_name` AS `group_name`, `e`.`lecturer_id` AS `lecturer_id`, `l`.`full_name` AS `lecturer_name`, `l`.`staff_id` AS `staff_id`, `cl`.`role` AS `lecturer_role`, `cl`.`is_primary` AS `is_primary` FROM ((((`student` `s` join `enrollment` `e` on(`s`.`student_id` = `e`.`student_id`)) join `class` `c` on(`e`.`class_id` = `c`.`class_id`)) join `lecturer` `l` on(`e`.`lecturer_id` = `l`.`lecturer_id`)) join `course_lecturer` `cl` on(`c`.`class_id` = `cl`.`class_id` and `l`.`lecturer_id` = `cl`.`lecturer_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_submissions_with_group`
--
DROP TABLE IF EXISTS `v_submissions_with_group`;

CREATE ALGORITHM=UNDEFINED DEFINER=`` SQL SECURITY DEFINER VIEW `v_submissions_with_group`  AS SELECT 'essay' AS `submission_type`, `es`.`id` AS `submission_id`, `es`.`assignment_id` AS `assignment_id`, `es`.`student_id` AS `student_id`, `es`.`final_grade` AS `final_grade`, `es`.`submitted_at` AS `submitted_at`, `a`.`class_id` AS `class_id`, `a`.`group_id` AS `group_id`, `a`.`lecturer_id` AS `assignment_lecturer_id`, `e`.`lecturer_id` AS `student_lecturer_id` FROM ((`essay_submission` `es` join `assignment` `a` on(`es`.`assignment_id` = `a`.`assignment_id`)) join `enrollment` `e` on(`es`.`student_id` = `e`.`student_id` and `a`.`class_id` = `e`.`class_id`))union all select 'code' AS `submission_type`,`cs`.`id` AS `submission_id`,`cs`.`assignment_id` AS `assignment_id`,`cs`.`student_id` AS `student_id`,`cs`.`final_grade` AS `final_grade`,`cs`.`submitted_at` AS `submitted_at`,`a`.`class_id` AS `class_id`,`a`.`group_id` AS `group_id`,`a`.`lecturer_id` AS `assignment_lecturer_id`,`e`.`lecturer_id` AS `student_lecturer_id` from ((`code_submission` `cs` join `assignment` `a` on(`cs`.`assignment_id` = `a`.`assignment_id`)) join `enrollment` `e` on(`cs`.`student_id` = `e`.`student_id` and `a`.`class_id` = `e`.`class_id`))  ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assignment`
--
ALTER TABLE `assignment`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `fk_assignment_lecturer` (`lecturer_id`),
  ADD KEY `fk_assignment_group` (`group_id`);

--
-- Indexes for table `chat_log`
--
ALTER TABLE `chat_log`
  ADD PRIMARY KEY (`chat_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `chat_message`
--
ALTER TABLE `chat_message`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `chat_session`
--
ALTER TABLE `chat_session`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_updated` (`updated_at`);

--
-- Indexes for table `class`
--
ALTER TABLE `class`
  ADD PRIMARY KEY (`class_id`),
  ADD UNIQUE KEY `class_code` (`class_code`),
  ADD KEY `coordinator_id` (`coordinator_id`);

--
-- Indexes for table `code_submission`
--
ALTER TABLE `code_submission`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assignment_id` (`assignment_id`);

--
-- Indexes for table `course_lecturer`
--
ALTER TABLE `course_lecturer`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assignment` (`class_id`,`lecturer_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `lecturer_id` (`lecturer_id`),
  ADD KEY `fk_course_lecturer_group` (`group_id`);

--
-- Indexes for table `enrollment`
--
ALTER TABLE `enrollment`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD UNIQUE KEY `unique_enrollment` (`student_id`,`class_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `fk_enrollment_lecturer` (`lecturer_id`);

--
-- Indexes for table `essay_submission`
--
ALTER TABLE `essay_submission`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assignment_id` (`assignment_id`);

--
-- Indexes for table `group_lecturer`
--
ALTER TABLE `group_lecturer`
  ADD PRIMARY KEY (`group_id`),
  ADD UNIQUE KEY `unique_group_per_class` (`class_id`,`group_name`),
  ADD KEY `lecturer_id` (`lecturer_id`);

--
-- Indexes for table `lecturer`
--
ALTER TABLE `lecturer`
  ADD PRIMARY KEY (`lecturer_id`),
  ADD UNIQUE KEY `staff_id` (`staff_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `performance_report`
--
ALTER TABLE `performance_report`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `generated_by` (`generated_by`);

--
-- Indexes for table `plagiarism_report`
--
ALTER TABLE `plagiarism_report`
  ADD PRIMARY KEY (`report_id`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `matric_no` (`matric_no`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `student_group_history`
--
ALTER TABLE `student_group_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `old_lecturer_id` (`old_lecturer_id`),
  ADD KEY `new_lecturer_id` (`new_lecturer_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assignment`
--
ALTER TABLE `assignment`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `chat_log`
--
ALTER TABLE `chat_log`
  MODIFY `chat_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `chat_message`
--
ALTER TABLE `chat_message`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `chat_session`
--
ALTER TABLE `chat_session`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `class`
--
ALTER TABLE `class`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `code_submission`
--
ALTER TABLE `code_submission`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `course_lecturer`
--
ALTER TABLE `course_lecturer`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `enrollment`
--
ALTER TABLE `enrollment`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `essay_submission`
--
ALTER TABLE `essay_submission`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `group_lecturer`
--
ALTER TABLE `group_lecturer`
  MODIFY `group_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lecturer`
--
ALTER TABLE `lecturer`
  MODIFY `lecturer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `performance_report`
--
ALTER TABLE `performance_report`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `plagiarism_report`
--
ALTER TABLE `plagiarism_report`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `student`
--
ALTER TABLE `student`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `student_group_history`
--
ALTER TABLE `student_group_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assignment`
--
ALTER TABLE `assignment`
  ADD CONSTRAINT `assignment_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assignment_group` FOREIGN KEY (`group_id`) REFERENCES `class` (`class_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_assignment_lecturer` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturer` (`lecturer_id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_log`
--
ALTER TABLE `chat_log`
  ADD CONSTRAINT `chat_log_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_message`
--
ALTER TABLE `chat_message`
  ADD CONSTRAINT `chat_message_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `chat_session` (`session_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_message_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_session`
--
ALTER TABLE `chat_session`
  ADD CONSTRAINT `chat_session_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `class`
--
ALTER TABLE `class`
  ADD CONSTRAINT `class_ibfk_1` FOREIGN KEY (`coordinator_id`) REFERENCES `lecturer` (`lecturer_id`) ON DELETE SET NULL;

--
-- Constraints for table `code_submission`
--
ALTER TABLE `code_submission`
  ADD CONSTRAINT `code_submission_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignment` (`assignment_id`);

--
-- Constraints for table `course_lecturer`
--
ALTER TABLE `course_lecturer`
  ADD CONSTRAINT `course_lecturer_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_lecturer_ibfk_2` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturer` (`lecturer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_course_lecturer_group` FOREIGN KEY (`group_id`) REFERENCES `class` (`class_id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollment`
--
ALTER TABLE `enrollment`
  ADD CONSTRAINT `enrollment_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollment_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_enrollment_lecturer` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturer` (`lecturer_id`) ON DELETE CASCADE;

--
-- Constraints for table `essay_submission`
--
ALTER TABLE `essay_submission`
  ADD CONSTRAINT `essay_submission_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignment` (`assignment_id`);

--
-- Constraints for table `group_lecturer`
--
ALTER TABLE `group_lecturer`
  ADD CONSTRAINT `group_lecturer_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_lecturer_ibfk_2` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturer` (`lecturer_id`) ON DELETE CASCADE;

--
-- Constraints for table `performance_report`
--
ALTER TABLE `performance_report`
  ADD CONSTRAINT `performance_report_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `performance_report_ibfk_2` FOREIGN KEY (`generated_by`) REFERENCES `lecturer` (`lecturer_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_group_history`
--
ALTER TABLE `student_group_history`
  ADD CONSTRAINT `student_group_history_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_group_history_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_group_history_ibfk_3` FOREIGN KEY (`old_lecturer_id`) REFERENCES `lecturer` (`lecturer_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `student_group_history_ibfk_4` FOREIGN KEY (`new_lecturer_id`) REFERENCES `lecturer` (`lecturer_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
