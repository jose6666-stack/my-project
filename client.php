<?php
// Start session and enable error reporting
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize car records in the session
if (!isset($_SESSION['car_records'])) {
    $_SESSION['car_records'] = [];
}

// Define parking slots
$all_slots = ['A1', 'A2', 'A3', 'B1', 'B2', 'B3', 'C1', 'C2', 'C3'];

// Function to get available slots
function get_available_slots($all_slots, $car_records) {
    $assigned_slots = array_column($car_records, 'slot');
    return array_diff($all_slots, $assigned_slots);
}

// Get available slots
$available_slots = get_available_slots($all_slots, $_SESSION['car_records']);

$message = '';
$car_details = null;

// Handle form submission to add a car
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_car'])) {
    $car_name     = trim($_POST['car_name']);
    $owner_name   = trim($_POST['owner_name']);
    $car_color    = trim($_POST['car_color']);
    $number_plate = trim($_POST['number_plate']);
    $duration     = intval($_POST['duration']);

    if ($duration < 1 || $duration > 3) {
        $message = "Parking duration must be between 1 and 3 hours.";
    } else {
        $available_slots = get_available_slots($all_slots, $_SESSION['car_records']);
        if (!empty($available_slots)) {
            $assigned_slot = array_shift($available_slots);
            $charge = $duration * 300;
            $check_in_time = date("Y-m-d H:i:s");
            $check_out_time = null; // Placeholder for future functionality

            $_SESSION['car_records'][] = [
                'car_name'      => $car_name,
                'owner_name'    => $owner_name,
                'car_color'     => $car_color,
                'number_plate'  => $number_plate,
                'slot'          => $assigned_slot,
                'duration'      => $duration,
                'charge'        => $charge,
                'check_in_time' => $check_in_time,
                'check_out_time' => $check_out_time
            ];

            $car_details = end($_SESSION['car_records']); // Get the last added car details

            $message = "Car record added successfully! Assigned slot: $assigned_slot. Charge: Kshs $charge.";
        } else {
            $message = "No available parking slots!";
        }
    }
}

// Handle delete action
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_record'])) {
    $delete_index = intval($_POST['delete_index']);
    if (isset($_SESSION['car_records'][$delete_index])) {
        unset($_SESSION['car_records'][$delete_index]);
        $_SESSION['car_records'] = array_values($_SESSION['car_records']); // Re-index array
        $message = "Car record deleted successfully.";
    } else {
        $message = "Invalid record selected for deletion.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter Details As a Client</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
            padding: 0;
        }
        h1, h2 {
            margin-bottom: 20px;
        }
        form, .history {
            display: inline-block;
            text-align: left;
            margin: 20px auto;
        }
        form label {
            display: block;
            margin: 10px 0 5px;
        }
        form input, form button {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
        }
        form button {
            background-color: #007BFF;
            color: white;
            border: none;
            cursor: pointer;
        }
        form button:hover {
            background-color: #0056b3;
        }
        table {
            margin: 20px auto;
            border-collapse: collapse;
            width: 80%;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        table th {
            background-color: #f2f2f2;
        }
        .message {
            color: green;
            font-weight: bold;
            margin-top: 20px;
        }
        .delete-btn {
            background-color: #ff4d4d;
            color: white;
            border: none;
            cursor: pointer;
            padding: 5px 10px;
        }
        .delete-btn:hover {
            background-color: #cc0000;
        }
    </style>
</head>
<body>
    <h1>Client Car Parking Details</h1>

    <form method="POST" action="">
        <label for="car_name">Car Name:</label>
        <input type="text" name="car_name" id="car_name" required>

        <label for="owner_name">Owner Name:</label>
        <input type="text" name="owner_name" id="owner_name" required>

        <label for="car_color">Car Color:</label>
        <input type="text" name="car_color" id="car_color" required>

        <label for="number_plate">Number Plate:</label>
        <input type="text" name="number_plate" id="number_plate" required>

        <label for="duration">Parking Duration (1-3 hours):</label>
        <input type="number" name="duration" id="duration" min="1" max="3" required>

        <button type="submit" name="add_car">Submit</button>
    </form>

    <?php if ($message): ?>
        <p class="message"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <?php if (!empty($_SESSION['car_records'])): ?>
        <div class="history">
            <h2>Parking History</h2>
            <table>
                <thead>
                    <tr>
                        <th>Car Name</th>
                        <th>Owner Name</th>
                        <th>Car Color</th>
                        <th>Number Plate</th>
                        <th>Slot</th>
                        <th>Duration (hours)</th>
                        <th>Charge</th>
                        <th>Check-in Time</th>
                        <th>Check-out Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($_SESSION['car_records'] as $index => $record): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($record['car_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['owner_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['car_color']); ?></td>
                            <td><?php echo htmlspecialchars($record['number_plate']); ?></td>
                            <td><?php echo htmlspecialchars($record['slot']); ?></td>
                            <td><?php echo htmlspecialchars($record['duration']); ?></td>
                            <td><?php echo htmlspecialchars($record['charge']); ?></td>
                            <td><?php echo htmlspecialchars($record['check_in_time']); ?></td>
                            <td><?php echo htmlspecialchars($record['check_out_time'] ?? 'N/A'); ?></td>
                            <td>
                                <form method="POST" action="" style="margin: 0;">
                                    <input type="hidden" name="delete_index" value="<?php echo $index; ?>">
                                    <button type="submit" name="delete_record" class="delete-btn">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</body>
</html>