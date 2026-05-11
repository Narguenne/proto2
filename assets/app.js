const histogramCanvas = document.getElementById('histogram');
const histogramData = window.histogramData || { labels: [], values: [] };

if (histogramCanvas && typeof Chart !== 'undefined') {
    new Chart(histogramCanvas, {
        type: 'bar',
        data: {
            labels: histogramData.labels,
            datasets: [{
                label: 'Nombre de coureurs',
                data: histogramData.values,
                backgroundColor: '#00A3E0',
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return context.parsed.y + ' participant(s)';
                        }
                    }
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Temps (tranches de 1 minute)'
                    },
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Participants'
                    },
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}