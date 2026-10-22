<?php
/**
 * bmi.php
 * Handles:
 *   - action=calculate (POST): calculates BMI, stores it, returns result + tips
 *   - action=history   (GET) : returns past BMI records (for History section + chart)
 *   - action=delete    (POST): deletes a single history record by id
 *
 * All responses are JSON.
 */

header('Content-Type: application/json');
require_once 'db.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? 'calculate');

/* ---------------------------------------------------------
   Helper: determine BMI category + personalized tips
--------------------------------------------------------- */
function getBmiCategory(float $bmi): string {
    if ($bmi < 18.5) return 'Underweight';
    if ($bmi < 25)   return 'Normal';
    if ($bmi < 30)   return 'Overweight';
    return 'Obese';
}

function getHealthTips(string $category, string $gender, int $age): array {
    $tips = [
        'Underweight' => [
            'Increase your calorie intake with nutrient-dense foods like nuts, dairy, and whole grains.',
            'Add strength training to your routine to build healthy muscle mass.',
            'Eat frequent, smaller meals throughout the day rather than three large ones.',
            'Consult a nutritionist to design a healthy weight-gain plan.'
        ],
        'Normal' => [
            'Great job! Maintain your weight with a balanced diet and regular exercise.',
            'Aim for at least 150 minutes of moderate exercise per week.',
            'Keep an eye on portion sizes and stay hydrated throughout the day.',
            'Prioritize sleep — 7-9 hours a night supports a healthy metabolism.'
        ],
        'Overweight' => [
            'Incorporate more vegetables, fruits, and lean proteins into your meals.',
            'Aim for 30 minutes of moderate cardio at least 5 days a week.',
            'Reduce intake of sugary drinks and processed snacks.',
            'Track your meals to build awareness of portion sizes and calories.'
        ],
        'Obese' => [
            'Consult a healthcare provider to create a safe, personalized weight-loss plan.',
            'Start with low-impact activities like walking or swimming.',
            'Focus on whole foods and reduce refined sugar and saturated fat.',
            'Set small, achievable goals — even a 5-10% weight reduction improves health markers.'
        ],
    ];

    $selected = $tips[$category] ?? [];

    // A small age/gender-aware nudge added to personalize further
    if ($age >= 50) {
        $selected[] = 'At your age, include bone-strengthening activities like resistance training and ensure adequate calcium and vitamin D intake.';
    } elseif ($age <= 18) {
        $selected[] = 'As a younger individual, focus on building healthy lifelong habits rather than strict dieting.';
    }

    if (strtolower($gender) === 'female') {
        $selected[] = 'Ensure adequate iron and folate intake, especially important for women\'s health.';
    } elseif (strtolower($gender) === 'male') {
        $selected[] = 'Men often carry weight around the abdomen — combine cardio with core-strengthening exercises.';
    }

    return $selected;
}

$dailyQuotes = [
    "Take care of your body. It's the only place you have to live. — Jim Rohn",
    "Health is not valued until sickness comes. — Thomas Fuller",
    "A healthy outside starts from the inside. — Robert Urich",
    "The greatest wealth is health. — Virgil",
    "To keep the body in good health is a duty. — Buddha",
    "Physical fitness is the first requisite of happiness. — Joseph Pilates",
    "Small daily improvements lead to staggering long-term results."
];

/* ---------------------------------------------------------
   ACTION: calculate
--------------------------------------------------------- */
if ($action === 'calculate' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $height = filter_input(INPUT_POST, 'height', FILTER_VALIDATE_FLOAT);
    $weight = filter_input(INPUT_POST, 'weight', FILTER_VALIDATE_FLOAT);
    $age    = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
    $gender = trim($_POST['gender'] ?? '');
    $userId = isset($_POST['user_id']) && $_POST['user_id'] !== ''
        ? filter_var($_POST['user_id'], FILTER_VALIDATE_INT)
        : null;

    // ---- Validation ----
    $errors = [];
    if (!$height || $height <= 0 || $height > 300) $errors[] = 'Please enter a valid height in cm (1-300).';
    if (!$weight || $weight <= 0 || $weight > 500) $errors[] = 'Please enter a valid weight in kg (1-500).';
    if ($age === false || $age === null || $age <= 0 || $age > 130) $errors[] = 'Please enter a valid age.';
    if (!in_array(strtolower($gender), ['male', 'female', 'other'])) $errors[] = 'Please select a gender.';

    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit;
    }

    // ---- BMI calculation: weight(kg) / (height(m))^2 ----
    $heightM = $height / 100;
    $bmi = $weight / ($heightM * $heightM);
    $bmi = round($bmi, 2);
    $category = getBmiCategory($bmi);
    $tips = getHealthTips($category, $gender, $age);

    // ---- Store in database using a prepared statement ----
    $stmt = $conn->prepare(
        "INSERT INTO bmi_records (user_id, height, weight, age, gender, bmi_value, category, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param(
        'iddisds',
        $userId,
        $height,
        $weight,
        $age,
        $gender,
        $bmi,
        $category
    );
    // 'iddisds' -> user_id(int), height(double), weight(double),
    // age(int), gender(string), bmi(double), category(string)

    $saved = $stmt->execute();
    $insertId = $stmt->insert_id;
    $stmt->close();

    echo json_encode([
        'success'   => true,
        'saved'     => $saved,
        'id'        => $insertId,
        'bmi'       => $bmi,
        'category'  => $category,
        'tips'      => $tips,
        'quote'     => $dailyQuotes[array_rand($dailyQuotes)],
        'input'     => [
            'height' => $height,
            'weight' => $weight,
            'age'    => $age,
            'gender' => $gender
        ]
    ]);
    exit;
}

/* ---------------------------------------------------------
   ACTION: history  (GET) — returns past records
--------------------------------------------------------- */
if ($action === 'history' && $_SERVER['REQUEST_METHOD'] === 'GET') {

    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
    $limit = max(1, min($limit, 100)); // clamp between 1 and 100

    $stmt = $conn->prepare(
        "SELECT id, user_id, height, weight, age, gender, bmi_value, category, created_at
         FROM bmi_records
         ORDER BY created_at DESC
         LIMIT ?"
    );
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'records' => $records]);
    exit;
}

/* ---------------------------------------------------------
   ACTION: delete (POST) — remove one history record
--------------------------------------------------------- */
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid record id.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM bmi_records WHERE id = ?");
    $stmt->bind_param('i', $id);
    $deleted = $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => $deleted]);
    exit;
}

/* ---------------------------------------------------------
   Fallback: unknown action / method
--------------------------------------------------------- */
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid request.']);
$conn->close();
