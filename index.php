<?php
// Start session and enable error reporting
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Message to show feedback to the user
$message = '';
$page = isset($_GET['page']) ? $_GET['page'] : 'login';
// Initialize car records, parking slots, and parking slot history in the session
if (!isset($_SESSION['car_records'])) {
    $_SESSION['car_records'] = [];
}
if (!isset($_SESSION['parking_slot_history'])) {
    $_SESSION['parking_slot_history'] = [];
}
// Define a fixed set of parking slots
$all_slots = ['A1', 'A2', 'A3', 'B1', 'B2', 'B3', 'C1', 'C2', 'C3'];
// Determine available slots based on assigned records
function get_available_slots($all_slots, $car_records) {
    $assigned_slots = array_column($car_records, 'slot');
    return array_diff($all_slots, $assigned_slots);
}
$available_slots = get_available_slots($all_slots, $_SESSION['car_records']);
// Define admin credentials in the session (no user role)
if (!isset($_SESSION['users'])) {
    $_SESSION['users'] = [
        'admin@example.com' => ['password' => '123', 'role' => 'admin'],
    ];
}
$users = &$_SESSION['users'];
// Handle form submissions based on the page
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($page == 'login') {
        $email = $_POST['email'];
        $password = $_POST['password'];
        if (isset($users[$email]) && $users[$email]['password'] == $password) {
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $users[$email]['role'];
            header('Location: ?page=dashboard');
            exit();
        } else {
            $message = "Invalid email or password";
        }
    } elseif ($page == 'dashboard' && isset($_SESSION['email'])) {
        if (isset($_POST['add_car']) && $_SESSION['role'] === 'admin') {
            $car_name = $_POST['car_name'];
            $owner_name = $_POST['owner_name'];
            $car_color = $_POST['car_color'];
            $number_plate = $_POST['number_plate'];
            $duration = intval($_POST['duration']);
            if ($duration < 1 || $duration > 3) {
                $message = "Parking duration must be between 1 and 3 hours.";
            } else {
                $available_slots = get_available_slots($all_slots, $_SESSION['car_records']);
                if (!empty($available_slots)) {
                    $assigned_slot = array_shift($available_slots);
                    $charge = $duration * 300;
                    $check_in_time = date("Y-m-d H:i:s");
                    $_SESSION['car_records'][] = [
                        'car_name' => $car_name,
                        'owner_name' => $owner_name,
                        'car_color' => $car_color,
                        'number_plate' => $number_plate,
                        'slot' => $assigned_slot,
                        'duration' => $duration,
                        'charge' => $charge,
                        'check_in_time' => $check_in_time,
                        'check_out_time' => null,
                        'date_added' => $check_in_time
                    ];
                    $_SESSION['parking_slot_history'][] = [
                        'slot' => $assigned_slot,
                        'car_name' => $car_name,
                        'number_plate' => $number_plate,
                        'action' => 'Check-In',
                        'date' => $check_in_time
                    ];
                    $message = "Car record added successfully! Assigned slot: $assigned_slot. Charge: Kshs $charge.";
                } else {
                    $message = "No available parking slots!";
                }
            }
        }
        if (isset($_POST['check_out']) && $_SESSION['role'] === 'admin') {
            $index = $_POST['record_index'];
            if (isset($_SESSION['car_records'][$index])) {
                $car_record = $_SESSION['car_records'][$index];
                $check_out_time = date("Y-m-d H:i:s");
                $check_in_time = strtotime($car_record['check_in_time']);
                $check_out_time_timestamp = strtotime($check_out_time);
                $duration_in_seconds = $check_out_time_timestamp - $check_in_time;
                $duration_in_hours = ceil($duration_in_seconds / 3600);
                $charge = $car_record['charge'];
                $_SESSION['car_records'][$index]['check_out_time'] = $check_out_time;
                $_SESSION['car_records'][$index]['charge'] = $charge;
                $_SESSION['parking_slot_history'][] = [
                    'slot' => $car_record['slot'],
                    'car_name' => $car_record['car_name'],
                    'number_plate' => $car_record['number_plate'],
                    'action' => 'Check-Out',
                    'date' => $check_out_time
                ];
                $message = "Car checked out successfully! Total charge: Kshs $charge.";
            } else {
                $message = "Invalid car record index.";
            }
        }
        if (isset($_POST['generate_csv']) && $_SESSION['role'] === 'admin') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="parking_report.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Car Name', 'Owner Name', 'Car Color', 'Number Plate', 'Slot', 'Duration', 'Charge', 'Check-In Time', 'Check-Out Time']);
            foreach ($_SESSION['car_records'] as $record) {
                fputcsv($output, [
                    $record['car_name'],
                    $record['owner_name'],
                    $record['car_color'],
                    $record['number_plate'],
                    $record['slot'],
                    $record['duration'],
                    $record['charge'],
                    $record['check_in_time'],
                    $record['check_out_time']
                ]);
            }
            fclose($output);
            exit();
        }
    }
}
if ($page == 'logout') {
    session_destroy();
    header('Location: ?page=login');
    exit();
}
$search_criteria = isset($_POST['search']) ? $_POST['search'] : '';
$filtered_records = $_SESSION['car_records'];
if (!empty($search_criteria)) {
    $filtered_records = array_filter($_SESSION['car_records'], function($record) use ($search_criteria) {
        return stripos($record['car_name'], $search_criteria) !== false ||
               stripos($record['owner_name'], $search_criteria) !== false ||
               stripos($record['car_color'], $search_criteria) !== false ||
               stripos($record['slot'], $search_criteria) !== false ||
               stripos($record['number_plate'], $search_criteria) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($page); ?></title>
    <style>
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        .container { width: 90%; margin: 0 auto; }
        .message, .error { color: red; }
        button, input[type="submit"] { background-color: #4CAF50; color: white; padding: 10px 15px; border: none; cursor: pointer; }
        button:hover, input[type="submit"]:hover { background-color: #45a049; }
        .form-container { margin-top: 20px; }
        .logout-btn { background-color: #f44336; }
        .logout-btn:hover { background-color: #e53935; }
        h1 { text-align: center; margin-top: 50px; }
        h2 { text-align: center; }
    </style>
</head>
<body>
    <?php if ($page == 'login'): ?>
        <div class="container">
            <h1>Ngara Car Parking System</h1>
            <h2>Login As Admin</h2>
            <form method="POST" action="?page=login" style="text-align: center;">
                <label>Email: </label><input type="email" name="email" required><br><br>
                <label>Password: </label><input type="password" name="password" required><br><br>
                <button type="submit">Login</button>
            </form>
            <!-- Button to open the new Client Details page in a popup -->
            <button type="button" onclick="window.open('client.php', 'ClientDetails', 'width=600,height=600')">Client Details</button>
            <?php if ($message): ?>
                <p class="message"><?php echo $message; ?></p>
            <?php endif; ?>
        </div>
    <?php elseif ($page == 'dashboard' && isset($_SESSION['email'])): ?>
        <div class="container">
            <h1>NGARA CAR PARKING SYSTEM</h1>
            <h2>Welcome, <?php echo $_SESSION['email']; ?></h2>
            <form method="POST" action="?page=dashboard">
                <h3>Add New Car</h3>
                <label>Car Name:</label><input type="text" name="car_name" required><br><br>
                <label>Owner Name:</label><input type="text" name="owner_name" required><br><br>
                <label>Car Color:</label><input type="text" name="car_color" required><br><br>
                <label>Number Plate:</label><input type="text" name="number_plate" required><br><br>
                <label>Duration (hours):</label><input type="number" name="duration" min="1" max="3" required><br><br>
                <button type="submit" name="add_car">Add Car</button>
            </form>
            <h3>Search Car Records</h3>
            <form method="POST" action="?page=dashboard">
                <input type="text" name="search" placeholder="Search by car name, owner, or number plate">
                <button type="submit">Search</button>
            </form>
            <h3>Available Slots</h3>
            <p>Available Parking Slots: <?php echo implode(", ", $available_slots); ?></p>
            <h3>Car Records</h3>
            <table>
                <tr>
                    <th>Car Name</th>
                    <th>Owner Name</th>
                    <th>Car Color</th>
                    <th>Number Plate</th>
                    <th>Slot</th>
                    <th>Duration</th>
                    <th>Charge</th>
                    <th>Check-In Time</th>
                    <th>Check-Out Time</th>
                    <th>Action</th>
                </tr>
                <?php foreach ($filtered_records as $index => $record): ?>
                    <tr>
                        <td><?php echo $record['car_name']; ?></td>
                        <td><?php echo $record['owner_name']; ?></td>
                        <td><?php echo $record['car_color']; ?></td>
                        <td><?php echo $record['number_plate']; ?></td>
                        <td><?php echo $record['slot']; ?></td>
                        <td><?php echo $record['duration']; ?></td>
                        <td><?php echo $record['charge']; ?></td>
                        <td><?php echo $record['check_in_time']; ?></td>
                        <td><?php echo $record['check_out_time'] ; ?></td>
            
                        <td>
                            <?php if ($_SESSION['role'] === 'admin' && $record['check_out_time'] === null): ?>
                                <form method="POST" action="?page=dashboard" style="display:inline;">
                                    <input type="hidden" name="record_index" value="<?php echo $index; ?>">
                                    <button type="submit" name="check_out">Check-Out</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php if ($message): ?>
                <p class="message"><?php echo $message; ?></p>
            <?php endif; ?>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <form method="POST" action="?page=dashboard" style="margin-top: 20px;">
                    <button type="submit" name="generate_csv">Generate CSV Report</button>
                </form>
            <?php endif; ?>
            <br><br>
            <!-- Button to open the new Client Details page in a popup -->
            <button type="button" onclick="window.open('client.php', 'ClientDetails', 'width=600,height=600')">Client Details</button>
            <br><br>
            <form method="POST" action="?page=logout">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    <?php endif; ?>
</body>
</html>
