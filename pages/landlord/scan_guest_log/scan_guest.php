<!DOCTYPE html>
<html>

<head>
    <title>Scan Guest QR Code</title>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        #reader {
            width: 300px;
            margin: auto;
        }

        body {
            text-align: center;
            font-family: Arial;
            padding: 30px;
        }
    </style>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../css/addcontent.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script>
        function logout() {
            var msg = 'Are you sure you want to logout?';
            Swal.fire({
                icon: 'question',
                title: 'Log Out',
                text: msg,
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../logout.php?status=logout';
                } else {
                    location.reload();
                }
            });
        }
    </script>
</head>

<body>
    <main class="main-content">
        <div class="form-section">
            <div class="guest-pass-container">

                <div class="form-header">
                    <i class="fas fa-camera fa-2x"></i>
                    <h1>Scan QR Code</h1>
                </div>
                <div id="reader"></div>
                <p id="result"></p>
                <button class="back-btn" onclick="window.location.href='../tenant_manage.php'">Back</button>


                <script>
                    function handleScan(decodedText) {
                        // Send QR data to backend
                        fetch("lookup_guest.php", {
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/x-www-form-urlencoded"
                                },
                                body: "qrdata=" + encodeURIComponent(decodedText)
                            })
                            .then(response => response.text())
                            .then(data => {
                                document.getElementById("result").innerHTML = data;
                            });
                    }

                    const html5QrCode = new Html5Qrcode("reader");
                    Html5Qrcode.getCameras().then(devices => {
                        if (devices.length) {
                            html5QrCode.start({
                                    facingMode: "environment"
                                }, // Use rear camera
                                {
                                    fps: 10,
                                    qrbox: 250
                                },
                                handleScan
                            );
                        }
                    });
                </script>
            </div>
        </div>
    </main>

    <script src="../../js/script.js"></script>

</body>

</html>