document.addEventListener("DOMContentLoaded", function () {
    google.charts.load("current", { packages: ["corechart"] });
    google.charts.setOnLoadCallback(drawColumnChart);

    function drawColumnChart() {
        var data = google.visualization.arrayToDataTable([
            ["Status", "Count", { role: "style" }],
            ["Paid on Time", 112, "#d7c97f"],
            ["Late Payment", 88, "#d7c97f"]
        ]);

        var options = {
            title: "",
            chartArea: { width: "80%", height: "70%" },
            legend: { position: "none" },
            vAxis: {
                minValue: 0,
                gridlines: { count: 5 },
                textStyle: { color: "#333" }
            },
            hAxis: {
                textStyle: { color: "#333" }
            },
            height: 250,
            backgroundColor: "transparent"
        };

        var chart = new google.visualization.ColumnChart(document.getElementById("payment-chart"));
        chart.draw(data, options);
    }
});