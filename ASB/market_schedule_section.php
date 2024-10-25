<?php
$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$marketHours = [];
$stmt = $conn->prepare("SELECT day_of_week, open_time, close_time, is_open FROM default_market_hours WHERE day_of_week = ?");
if (!$stmt) {
    echo "Error preparing statement: " . $conn->error;
    exit();
}

foreach ($daysOfWeek as $day) {
    $stmt->bind_param("s", $day);
    $stmt->execute();
    $stmt->bind_result($dayOfWeek, $openTime, $closeTime, $isOpen);
    $stmt->fetch();
    $marketHours[$dayOfWeek] = [
        'open_time' => $openTime,
        'close_time' => $closeTime,
        'is_open' => $isOpen
    ];
}

$stmt->close();
?>
<div class="market-schedule-container">
    <h2>Market Schedule</h2>
    <div id="market-calendar">
		<div class="calendar-nav">
			<button id="today-btn">Today</button>
			<button id="prev-month" class="nav-btn">
				<span>&#9664;</span>
			</button>
			<button id="next-month" class="nav-btn">
				<span>&#9654;</span>
			</button>
			<div id="calendar-month-year"></div>
		</div>
        <table id="calendar-table">
            <thead>
                <tr>
                    <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th>
                    <th>Thu</th><th>Fri</th><th>Sat</th>
                </tr>
            </thead>
            <tbody id="calendar-body">
            </tbody>
        </table>
    </div>
	
	
	<div id="event-modal" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h3>Add Market Event</h3>
        <form id="event-form">
            <label for="event-date">Event Date:</label>
            <input type="text" id="event-date" name="event_date" readonly>

            <label for="event-type">Event Type:</label>
            <select id="event-type" name="event_type">
                <option value="holiday">Holiday</option>
                <option value="special">Special</option>
                <option value="early_closure">Early Closure</option>
            </select>

            <label for="open-time">Open Time:</label>
            <input type="time" id="open-time" name="open_time">

            <label for="close-time">Close Time:</label>
            <input type="time" id="close-time" name="close_time">

            <label for="event-description">Description:</label>
            <textarea id="event-description" name="event_description" placeholder="Enter event description..."></textarea>

            <button type="submit">Save Event</button>
        </form>
    </div>
</div>
	

    <h3>Weekly Schedule</h3>
    <form id="weekly-schedule-form" action="update_weekly_schedule.php" method="POST">
        <?php foreach ($daysOfWeek as $day): ?>
        <div class="day-schedule" data-day="<?php echo $day; ?>">
            <h4><?php echo $day; ?></h4>
            <div class="schedule-item">
                <label for="open_time_<?php echo strtolower($day); ?>">Open Time:</label>
                <input type="time" id="open_time_<?php echo strtolower($day); ?>" name="open_time[<?php echo $day; ?>]" value="<?php echo isset($marketHours[$day]['open_time']) ? $marketHours[$day]['open_time'] : ''; ?>" required>
            </div>
            <div class="schedule-item">
                <label for="close_time_<?php echo strtolower($day); ?>">Close Time:</label>
                <input type="time" id="close_time_<?php echo strtolower($day); ?>" name="close_time[<?php echo $day; ?>]" value="<?php echo isset($marketHours[$day]['close_time']) ? $marketHours[$day]['close_time'] : ''; ?>" required>
            </div>
            <div class="schedule-item">
                <label for="is_open_<?php echo strtolower($day); ?>">Is Open:</label>
                <input type="checkbox" id="is_open_<?php echo strtolower($day); ?>" name="is_open[<?php echo $day; ?>]" <?php echo (isset($marketHours[$day]['is_open']) && $marketHours[$day]['is_open'] == 1) ? 'checked' : ''; ?>>
            </div>
        </div>
        <?php endforeach; ?>
        <button type="submit">Update Weekly Schedule</button>
    </form>

</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    let currentDate = new Date();
    const today = new Date();

    function renderCalendar(date) {
        const monthYear = document.getElementById('calendar-month-year');
        const calendarBody = document.getElementById('calendar-body');
        calendarBody.innerHTML = '';
        const month = date.getMonth();
        const year = date.getFullYear();
        monthYear.textContent = `${date.toLocaleString('default', { month: 'long' })} ${year}`;
        const firstDay = new Date(year, month, 1).getDay();
        const totalDays = new Date(year, month + 1, 0).getDate();

        let dateCount = 1;
        for (let i = 0; i < 6; i++) {
            const row = document.createElement('tr');
            for (let j = 0; j < 7; j++) {
                const cell = document.createElement('td');
                if (i === 0 && j < firstDay) {
                    cell.classList.add('disabled');
                    cell.textContent = '';
                } else if (dateCount > totalDays) {
                    cell.classList.add('disabled');
                    cell.textContent = '';
                } else {
                    cell.textContent = dateCount;
                    if (
                        dateCount === today.getDate() &&
                        month === today.getMonth() &&
                        year === today.getFullYear()
                    ) {
                        cell.classList.add('today');
                    }
                    cell.dataset.date = `${year}-${String(month + 1).padStart(2, '0')}-${String(dateCount).padStart(2, '0')}`;
                    
                    dateCount++;
                }
                row.appendChild(cell);
            }
            calendarBody.appendChild(row);
            if (dateCount > totalDays) {
                break;
            }
        }
        fetchAndDisplayEvents(month + 1, year);
        attachCellClickEvents();
    }

    function attachCellClickEvents() {
        const calendarCells = document.querySelectorAll("#calendar-table td");
        calendarCells.forEach(cell => {
            cell.addEventListener("click", function() {
                if (cell.textContent && !cell.classList.contains('disabled')) {
                    const clickedDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), parseInt(cell.textContent));
                    openModal(clickedDate);
                }
            });
        });
    }

    function fetchAndDisplayEvents(month, year) {
        fetch(`fetch_market_events.php?month=${month}&year=${year}`)
            .then(response => response.json())
            .then(events => {
                const calendarCells = document.querySelectorAll("#calendar-table td");
                calendarCells.forEach(cell => {
                    if (cell.dataset.date) {
                        const cellDate = cell.dataset.date;
                        const eventsOnThisDate = events.filter(event => event.event_date === cellDate);

                        if (eventsOnThisDate.length > 0) {
                            const eventContainer = document.createElement('div');
                            eventContainer.classList.add('event-container');

                            eventsOnThisDate.forEach(event => {
                                const eventDiv = document.createElement('div');
                                eventDiv.classList.add('event-item');
                                const time = event.open_time ? `${event.open_time}` : 'All day';
                                eventDiv.innerHTML = `<span class="event-time">${time}</span> <span class="event-title">${event.event_description}</span>`;
                                
                                eventContainer.appendChild(eventDiv);
                            });
                            cell.appendChild(eventContainer);
                        }
                    }
                });
            })
            .catch(error => console.error("Error fetching events:", error));
    }

    document.getElementById('prev-month').addEventListener('click', function() {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar(currentDate);
    });

    document.getElementById('next-month').addEventListener('click', function() {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar(currentDate);
    });

    document.getElementById('today-btn').addEventListener('click', function() {
        currentDate = new Date();
        renderCalendar(currentDate);
    });
    renderCalendar(currentDate);
    const modal = document.getElementById("event-modal");
    const closeBtn = document.querySelector(".close-btn");
    const eventForm = document.getElementById("event-form");
    const eventDateInput = document.getElementById("event-date");

    function openModal(date) {
        eventDateInput.value = date.toISOString().split('T')[0];
        modal.style.display = "block";
    }

    function closeModal() {
        modal.style.display = "none";
    }

    closeBtn.addEventListener("click", closeModal);
    window.addEventListener("click", function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    eventForm.addEventListener("submit", function(event) {
        event.preventDefault();
        
        const formData = new FormData(eventForm);
        const eventDetails = {
            event_date: formData.get("event_date"),
            event_type: formData.get("event_type"),
            open_time: formData.get("open_time"),
            close_time: formData.get("close_time"),
            event_description: formData.get("event_description"),
        };

        fetch("save_event.php", {
            method: "POST",
            body: JSON.stringify(eventDetails),
            headers: {
                "Content-Type": "application/json"
            }
        })
        .then(response => response.json())
        .then(data => {
            alert("Event saved successfully!");
            closeModal();
            renderCalendar(currentDate);
        })
        .catch(error => console.error("Error saving event:", error));
    });
});
</script>

