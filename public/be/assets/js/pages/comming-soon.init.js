function startCountdown(targetDate) {
  const d = document.getElementById("days"),
        h = document.getElementById("hours"),
        m = document.getElementById("minutes"),
        s = document.getElementById("seconds");

  const update = () => {
    let diff = new Date(targetDate) - new Date();
    if (diff <= 0) return [d,h,m,s].forEach(el => el.textContent = "00");

    let days = Math.floor(diff / 86400000),
        hours = Math.floor(diff / 3600000) % 24,
        mins = Math.floor(diff / 60000) % 60,
        secs = Math.floor(diff / 1000) % 60;

    d.textContent = days.toString().padStart(2,'0');
    h.textContent = hours.toString().padStart(2,'0');
    m.textContent = mins.toString().padStart(2,'0');
    s.textContent = secs.toString().padStart(2,'0');
  };

  update();
  setInterval(update, 1000);
}

// Example usage: launch date is Dec 31, 2025
startCountdown("2025-12-31T00:00:00");
