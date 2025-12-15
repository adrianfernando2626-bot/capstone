
document.addEventListener("DOMContentLoaded", function () {

    // === CHART.JS DASHBOARD (If using Chart.js) ===
    const lineCanvas = document.getElementById('lineChart');
    const donutCanvas = document.getElementById('donutChart');

    if (lineCanvas && donutCanvas) {
        const monthlyLabels = ["Dec", "Jan", "Feb", "Mar", "Apr", "May", "Jun"];
        const currentData = [30, 40, 28, 35, 90, 40, 60];
        const previousData = [20, 25, 22, 27, 30, 20, 29];

        const lineChart = new Chart(lineCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [
                    {
                        label: 'Last 6 Months',
                        data: currentData,
                        borderColor: '#007bff',
                        fill: false,
                        tension: 0.4,
                    },
                    {
                        label: 'Previous',
                        data: previousData,
                        borderColor: '#00e676',
                        fill: false,
                        tension: 0.4,
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    x: { title: { display: true, text: 'Month' } },
                    y: { beginAtZero: true }
                }
            }
        });

        const donutChart = new Chart(donutCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ["On-Time Payments", "Late Payments"],
                datasets: [{
                    data: [56, 44],
                    backgroundColor: ["#00e676", "#ff5252"],
                    borderWidth: 0
                }]
            },
            options: {
                cutout: "70%",
                plugins: {
                    legend: { display: true, position: 'bottom' }
                }
            }
        });

        const monthFilter = document.getElementById('month-filter');
        if (monthFilter) {
            monthFilter.addEventListener('change', function () {
                const selectedMonth = this.value;
                if (selectedMonth === 'all') {
                    lineChart.data.labels = monthlyLabels;
                    lineChart.data.datasets[0].data = currentData;
                    lineChart.data.datasets[1].data = previousData;
                } else {
                    const index = monthlyLabels.indexOf(selectedMonth);
                    lineChart.data.labels = [monthlyLabels[index]];
                    lineChart.data.datasets[0].data = [currentData[index]];
                    lineChart.data.datasets[1].data = [previousData[index]];
                }
                lineChart.update();
            });
        }
    }

    // === LOGIN/SIGNUP HANDLING ===
    const container = document.getElementById("container");
    const registerbtn = document.getElementById("register");
    const loginbtn = document.getElementById("login");
    const forgotPasswordLink = document.getElementById("forgot-password-link");
    const backToLoginButton = document.getElementById("back-to-login");
    const showTermsLink = document.getElementById("show-terms");
    const backToSignupButton = document.getElementById("back-to-signup");

    if (container) {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('signup')) container.classList.add("active");

        registerbtn?.addEventListener("click", () => container.classList.add("active"));
        loginbtn?.addEventListener("click", () => container.classList.remove("active"));

        forgotPasswordLink?.addEventListener("click", (e) => {
            e.preventDefault();
            container.classList.add("forgot-active");
        });

        backToLoginButton?.addEventListener("click", () => {
            container.classList.remove("forgot-active");
            container.classList.remove("terms-active");
        });

        showTermsLink?.addEventListener("click", (e) => {
            e.preventDefault();
            container.classList.add("terms-active");
            container.classList.remove("forgot-active");
        });

        backToSignupButton?.addEventListener("click", (e) => {
            e.preventDefault();
            container.classList.remove("terms-active");
        });

        const signupForm = document.getElementById("signupForm");

        document.getElementById("show-password")?.addEventListener("change", function () {
            const type = this.checked ? "text" : "password";
            document.getElementById("password").type = type;
            document.getElementById("confirm_password").type = type;
        });

        document.getElementById("show-login-password-admin")?.addEventListener("change", function () {
            const type = this.checked ? "text" : "password";
            document.getElementById("admin_password").type = type;
            document.getElementById("confirm_admin_password").type = type;
        });

        document.getElementById("show-login-password")?.addEventListener("change", function () {
            document.getElementById("login_password").type = this.checked ? "text" : "password";
        });
    }


    const next2Btn = document.getElementById("signup-next-2");
    if (next2Btn) {
        next2Btn.addEventListener("click", function (e) {
            e.preventDefault();

            const step2 = document.getElementById("signup2");
            const step3 = document.getElementById("signup3");

            const last_name = document.getElementById("last_name").value.trim();
            const first_name = document.getElementById("first_name").value.trim();
            const phone_number = document.getElementById("phone_number").value.trim();
            const address = document.getElementById("address").value.trim();
            const email = document.querySelector("input[name='email']").value.trim();
            const password = document.getElementById("password").value.trim();
            const confirmPassword = document.getElementById("confirm_password").value.trim();
            const birthdate = document.getElementById("birthdate").value.trim();

            step2.style.display = "block";
            step3.style.display = "none";

            if (email === "" || password === "" || confirmPassword === "" || last_name === "" || first_name === "" || phone_number === "" || phone_number === "+639" || address === "" || birthdate === "") {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please fill in all fields before proceeding.',
                    confirmButtonColor: '#3085d6'
                }).then(() => {
                    step2.style.display = "block";
                    step3.style.display = "none";
                });
                return;
            }

            if (password !== confirmPassword) {
                Swal.fire({
                    icon: 'error',
                    title: 'Password Mismatch',
                    text: 'Password and Confirm Password do not match.',
                    confirmButtonColor: '#d33'
                }).then(() => {
                    step2.style.display = "block";
                    step3.style.display = "none";
                });
                return;
            }

            step2.style.display = "none";
            step3.style.display = "block";
        });
    }

    // === SIDEBAR TOGGLE ===
    const sidebar = document.querySelector(".side-bar");
    const burgerBtn = document.getElementById("burgerBtn");

    if (burgerBtn && sidebar) {
        if (localStorage.getItem("sidebarOpen") === "true") {
            sidebar.classList.add("open");
            document.body.classList.add("sidebar-open");
        }

        burgerBtn.addEventListener("click", () => {
            sidebar.classList.toggle("open");
            document.body.classList.toggle("sidebar-open");
            localStorage.setItem("sidebarOpen", sidebar.classList.contains("open"));
        });

        const navLinks = document.querySelectorAll(".side-bar a");
        navLinks.forEach(link => {
            link.addEventListener("click", () => {
                if (window.innerWidth < 768) {
                    sidebar.classList.remove("open");
                    document.body.classList.remove("sidebar-open");
                    localStorage.setItem("sidebarOpen", "false");
                }
            });
        });
    }

    // === GOOGLE CHARTS (LANDLORD DASHBOARD) ===
    if (document.getElementById('chart-div')) {
        google.charts.load('current', { 'packages': ['corechart'] });
        google.charts.setOnLoadCallback(() => {
            const lineData = google.visualization.arrayToDataTable([
                ['Month', 'Last 6 Months', 'Previous'],
                ['Dec', 30, 20], ['Jan', 40, 25], ['Feb', 28, 22],
                ['Mar', 35, 27], ['Apr', 90, 30], ['May', 40, 20], ['Jun', 60, 29]
            ]);

            const lineOptions = {
                curveType: 'function',
                legend: { position: 'top', alignment: 'center' },
                colors: ['#007bff', '#00e676'],
                lineWidth: 3,
                pointSize: 5,
                height: 260,
                backgroundColor: 'transparent',
                chartArea: { left: 40, top: 40, width: '90%', height: '65%' },
                hAxis: { textStyle: { color: '#888' } },
                vAxis: { textStyle: { color: '#888' } },
            };

            const lineChart = new google.visualization.LineChart(document.getElementById('chart-div'));
            lineChart.draw(lineData, lineOptions);

            const donutData = google.visualization.arrayToDataTable([
                ['Type', 'Percentage'],
                ['On-Time Payments', 56],
                ['Late Payments', 44]
            ]);

            const donutOptions = {
                pieHole: 0.7,
                colors: ['#00e676', '#ff5252'],
                pieSliceText: 'none',
                legend: 'none',
                chartArea: { width: '90%', height: '90%' },
                height: 250,
            };

            const donutChart = new google.visualization.PieChart(document.getElementById('donut-chart'));
            donutChart.draw(donutData, donutOptions);

            const percent = 56;
            const centerText = document.createElement("div");
            centerText.style.cssText = "position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:24px;color:#00e676;font-weight:bold;";
            centerText.innerText = percent + "%";
            const donutContainer = document.getElementById("donut-chart");
            donutContainer.style.position = "relative";
            donutContainer.appendChild(centerText);
        });
    }

    // === LOGOUT HANDLING ===
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function (e) {
            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: "You will be logged out.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, logout'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../logout.php';
                }
            });
        });
    }

    // === CHARTS CAROUSEL INIT ===
    (function initChartsCarousel() {
        const carousel = document.getElementById("chartsCarousel");
        if (!carousel) return;

        let currentSlide = 0;
        const prevBtn = document.getElementById("prevBtn");
        const nextBtn = document.getElementById("nextBtn");

        const updateTransform = () => {
            const slideWidth = carousel.parentElement.clientWidth;
            carousel.style.transform = `translateX(-${currentSlide * slideWidth}px)`;
        };

        const moveSlide = (direction) => {
            const totalSlides = carousel.children.length;
            currentSlide = (currentSlide + direction + totalSlides) % totalSlides;
            updateTransform();
        };

        prevBtn?.addEventListener("click", () => moveSlide(-1));
        nextBtn?.addEventListener("click", () => moveSlide(1));

        window.addEventListener("resize", updateTransform);
        updateTransform();
    })();

});