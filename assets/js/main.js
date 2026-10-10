/**
 * EventEase Main JavaScript Handlers
 */

document.addEventListener('DOMContentLoaded', function () {
    console.log('EventEase Application Loaded.');

    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});

/**
 * Generate QR code inside a target element container using QRCode library
 * @param {string} containerId ID of container element
 * @param {string} qrText Registration Code string
 */
function renderQRCode(containerId, qrText) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = '';
    
    if (typeof QRCode !== 'undefined') {
        new QRCode(container, {
            text: qrText,
            width: 180,
            height: 180,
            colorDark: "#0f172a",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    } else {
        container.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(qrText)}" alt="QR Code" class="img-fluid rounded border" />`;
    }
}

/**
 * Print Ticket Pass Function
 */
function printPassTicket(modalContentId) {
    const printContent = document.getElementById(modalContentId);
    if (!printContent) return;
    
    const printWindow = window.open('', '_blank', 'width=700,height=800');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>EventEase Registration Ticket</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { padding: 30px; font-family: sans-serif; }
                .no-print { display: none !important; }
                .ticket-card { border: 2px solid #0f172a; border-radius: 12px; padding: 20px; }
            </style>
        </head>
        <body>
            <div class="ticket-card">
                ${printContent.innerHTML}
            </div>
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() { window.close(); }, 500);
                };
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}
