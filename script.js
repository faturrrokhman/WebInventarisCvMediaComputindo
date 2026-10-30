document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const user = document.getElementById('username').value;
    const pass = document.getElementById('password').value;

    // Kirim data ke file login.php
    fetch('login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `username=${encodeURIComponent(user)}&password=${encodeURIComponent(pass)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Langsung pindah halaman tanpa alert konfirmasi
            window.location.href = 'dashboard.html'; 
        } else {
            // Alert hanya muncul jika login GAGAL
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Gagal terhubung ke server!');
    });
});