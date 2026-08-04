<!DOCTYPE html>
<html>
<head>
    <title>Connect WhatsApp</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>

    <div style="text-align: center; margin-top: 50px;">
        <h3>Scan QR Code untuk Login WhatsApp</h3>
        
        <div id="qrcode" style="display: inline-block;"></div>
    </div>

    <script>
        // Mengambil data dari controller
        var qrData = "{!! $qr_string !!}"; 

        // Generate QR Code
        new QRCode(document.getElementById("qrcode"), {
            text: qrData,
            width: 256,
            height: 256
        });
    </script>
</body>
</html>