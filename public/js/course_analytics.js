/**
 * Course Analytics JavaScript
 * Handles all client-side analytics functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize loading state
    let isLoading = false;
    
    // Initialize charts when document is ready
    initCharts();
    
    // Animate stat cards with a staggered delay
    animateStatCards();
    
    // Initialize tabs
    initializeTabs();
    
    // Initialize export dropdown
    initializeExport();
    
    // Initialize share functionality
    initializeShare();
    
    // Initialize lesson table functionality
    initializeLessonTable();
    
    // Handle refresh data button
    document.getElementById('refresh-data').addEventListener('click', function() {
        if (isLoading) return;
        refreshData();
    });
    
    // Handle custom period button
    document.getElementById('custom-period').addEventListener('click', function(e) {
        e.preventDefault();
        const dateRangeForm = document.getElementById('date-range-form');
        const isVisible = dateRangeForm.style.display === 'block';
        
        dateRangeForm.style.display = isVisible ? 'none' : 'block';
        
        // Add smooth animation
        if (!isVisible) {
            dateRangeForm.classList.add('animated-fade-in');
            setTimeout(() => {
                dateRangeForm.classList.remove('animated-fade-in');
            }, 600);
        }
    });
    
    // Handle cancel custom date button
    document.getElementById('cancel-custom-date')?.addEventListener('click', function() {
        document.getElementById('date-range-form').style.display = 'none';
    });
    
    // Ensure end date is always after start date
    const startDateInput = document.getElementById('start-date');
    const endDateInput = document.getElementById('end-date');
    
    if (startDateInput && endDateInput) {
        startDateInput.addEventListener('change', function() {
            if (endDateInput.value && startDateInput.value > endDateInput.value) {
                endDateInput.value = startDateInput.value;
            }
        });
        
        endDateInput.addEventListener('change', function() {
            if (startDateInput.value && endDateInput.value < startDateInput.value) {
                startDateInput.value = endDateInput.value;
            }
        });
    }
    
    // Add date constraints (can't select future dates)
    const today = new Date().toISOString().split('T')[0];
    if (startDateInput) startDateInput.setAttribute('max', today);
    if (endDateInput) endDateInput.setAttribute('max', today);
});

/**
 * Show loading indicators for all charts
 */
function showLoadingIndicators() {
    const chartContainers = document.querySelectorAll('.chart-card');
    
    chartContainers.forEach(container => {
        if (container.querySelector('canvas')) {
            const canvas = container.querySelector('canvas');
            const loadingIndicator = document.createElement('div');
            loadingIndicator.className = 'chart-loading';
            loadingIndicator.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i><span>Đang tải dữ liệu...</span>';
            
            // Insert loading indicator before canvas
            canvas.style.opacity = '0';
            canvas.parentNode.insertBefore(loadingIndicator, canvas);
        }
    });
}

/**
 * Hide all loading indicators
 */
function hideLoadingIndicators() {
    const loadingIndicators = document.querySelectorAll('.chart-loading');
    const canvases = document.querySelectorAll('.chart-card canvas');
    
    loadingIndicators.forEach(indicator => {
        indicator.classList.add('fade-out');
        setTimeout(() => {
            if (indicator.parentNode) {
                indicator.parentNode.removeChild(indicator);
            }
        }, 300);
    });
    
    canvases.forEach(canvas => {
        canvas.style.opacity = '0';
        setTimeout(() => {
            canvas.style.transition = 'opacity 0.5s ease';
            canvas.style.opacity = '1';
        }, 300);
    });
}

/**
 * Animates the stat cards with a staggered effect
 */
function animateStatCards() {
    const statCards = document.querySelectorAll('.stat-card');
    
    statCards.forEach((card, index) => {
        setTimeout(() => {
            card.classList.add('animated');
        }, 100 * (index + 1));
    });
}

/**
 * Initialize all charts with data from PHP
 */
function initCharts() {
    // Get chart colors based on current theme
    const colors = getThemeColors();
    
    // Ensure data variables exist or use defaults
    const safeDataLabels = typeof dateLabels !== 'undefined' ? dateLabels : [];
    const safeViewsData = typeof viewsData !== 'undefined' ? viewsData : [];
    const safeEnrollmentsData = typeof enrollmentsData !== 'undefined' ? enrollmentsData : [];
    const safeCompletionsData = typeof completionsData !== 'undefined' ? completionsData : [];
    
    // Ensure monthly data variables exist or use defaults
    const safeMonthLabels = typeof monthLabels !== 'undefined' ? monthLabels : [];
    const safeMonthlyViewsData = typeof monthlyViewsData !== 'undefined' ? monthlyViewsData : [];
    const safeMonthlyEnrollmentsData = typeof monthlyEnrollmentsData !== 'undefined' ? monthlyEnrollmentsData : [];
    const safeMonthlyCompletionsData = typeof monthlyCompletionsData !== 'undefined' ? monthlyCompletionsData : [];
    
    // Initialize trends chart if it exists
    const trendsCtx = document.getElementById('trendsChart');
    if (trendsCtx) {
        // Handle empty data case
        if (!safeDataLabels || safeDataLabels.length === 0) {
            displayNoDataMessage(trendsCtx, 'Không có dữ liệu cho khoảng thời gian này');
        } else {
            try {
                window.trendsChart = new Chart(trendsCtx, {
                    type: 'line',
                    data: {
                        labels: safeDataLabels,
                        datasets: [{
                            label: 'Lượt Xem',
                            data: safeViewsData,
                            backgroundColor: colors.views,
                            borderColor: colors.viewsBorder,
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: {
                            duration: 1000,
                            easing: 'easeOutQuart'
                        },
                        scales: {
                            x: {
                                grid: {
                                    color: colors.grid
                                },
                                ticks: {
                                    color: colors.text,
                                    maxRotation: 45,
                                    minRotation: 45
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: colors.grid
                                },
                                ticks: {
                                    color: colors.text,
                                    precision: 0
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                labels: {
                                    color: colors.text,
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    padding: 20
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                backgroundColor: colors.isDark ? '#2d3748' : 'rgba(255, 255, 255, 0.9)',
                                titleColor: colors.isDark ? '#fff' : '#1e293b',
                                bodyColor: colors.isDark ? '#e2e8f0' : '#4a5568',
                                borderColor: colors.isDark ? '#4a5568' : '#e2e8f0',
                                borderWidth: 1,
                                padding: 12,
                                cornerRadius: 8,
                                titleFont: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                bodyFont: {
                                    size: 13
                                },
                                displayColors: true,
                                boxPadding: 5
                            }
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        },
                        hover: {
                            mode: 'nearest',
                            intersect: false
                        }
                    }
                });
            } catch (error) {
                console.error('Error initializing trends chart:', error);
                displayNoDataMessage(trendsCtx, 'Lỗi khi tạo biểu đồ');
            }
        }
    }
    
    // Initialize monthly trends chart
    const monthlyTrendsCtx = document.getElementById('monthlyTrendsChart');
    if (monthlyTrendsCtx) {
        // Handle empty data case
        if (!safeMonthLabels || safeMonthLabels.length === 0) {
            displayNoDataMessage(monthlyTrendsCtx, 'Không có dữ liệu hàng tháng');
        } else {
            try {
                window.monthlyTrendsChart = new Chart(monthlyTrendsCtx, {
                    type: 'bar',
                    data: {
                        labels: safeMonthLabels,
                        datasets: [{
                            label: 'Lượt Xem Hàng Tháng',
                            data: safeMonthlyViewsData,
                            backgroundColor: colors.views,
                            borderColor: colors.viewsBorder,
                            borderWidth: 1,
                            borderRadius: 4,
                            maxBarThickness: 40
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: {
                            duration: 1000,
                            easing: 'easeOutQuart'
                        },
                        scales: {
                            x: {
                                grid: {
                                    color: colors.grid,
                                    display: false
                                },
                                ticks: {
                                    color: colors.text
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: colors.grid
                                },
                                ticks: {
                                    color: colors.text,
                                    precision: 0
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                labels: {
                                    color: colors.text,
                                    usePointStyle: true,
                                    pointStyle: 'rect',
                                    padding: 20
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                backgroundColor: colors.isDark ? '#2d3748' : 'rgba(255, 255, 255, 0.9)',
                                titleColor: colors.isDark ? '#fff' : '#1e293b',
                                bodyColor: colors.isDark ? '#e2e8f0' : '#4a5568',
                                borderColor: colors.isDark ? '#4a5568' : '#e2e8f0',
                                borderWidth: 1,
                                padding: 12,
                                cornerRadius: 8
                            }
                        },
                        interaction: {
                            mode: 'index',
                            intersect: false
                        }
                    }
                });
            } catch (error) {
                console.error('Error initializing monthly trends chart:', error);
                displayNoDataMessage(monthlyTrendsCtx, 'Lỗi khi tạo biểu đồ');
            }
        }
    }
    
    // Initialize enrollment sources chart
    const sourcesCtx = document.getElementById('enrollmentSourcesChart');
    if (sourcesCtx) {
        try {
            window.sourcesChart = new Chart(sourcesCtx, {
                type: 'doughnut',
                data: {
                    labels: sourceLabels,
                    datasets: [{
                        data: sourceData,
                        backgroundColor: [
                            'rgba(98, 74, 242, 0.8)',
                            'rgba(49, 151, 149, 0.8)',
                            'rgba(76, 201, 240, 0.8)',
                            'rgba(247, 37, 133, 0.8)',
                            'rgba(255, 159, 28, 0.8)'
                        ],
                        borderColor: [
                            'rgba(98, 74, 242, 1)',
                            'rgba(49, 151, 149, 1)',
                            'rgba(76, 201, 240, 1)',
                            'rgba(247, 37, 133, 1)',
                            'rgba(255, 159, 28, 1)'
                        ],
                        borderWidth: 2,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    animation: {
                        animateRotate: true,
                        animateScale: true,
                        duration: 1200,
                        easing: 'easeOutQuart'
                    },
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: colors.text,
                                padding: 15,
                                usePointStyle: true,
                                pointStyle: 'rectRounded',
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: colors.isDark ? '#2d3748' : 'rgba(255, 255, 255, 0.9)',
                            titleColor: colors.isDark ? '#fff' : '#1e293b',
                            bodyColor: colors.isDark ? '#e2e8f0' : '#4a5568',
                            borderColor: colors.isDark ? '#4a5568' : '#e2e8f0',
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${percentage}% (${value})`;
                                }
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error initializing sources chart:', error);
            displayNoDataMessage(sourcesCtx, 'Lỗi khi tạo biểu đồ');
        }
    }
    
    // Initialize demographic charts if they exist
    if (typeof ageLabels !== 'undefined' && typeof ageData !== 'undefined') {
        const ageCtx = document.getElementById('ageChart');
        if (ageCtx) {
            try {
                window.ageChart = createPieChart(ageCtx, ageLabels, ageData, 'pie');
            } catch (error) {
                console.error('Error initializing age chart:', error);
                displayNoDataMessage(ageCtx, 'Lỗi khi tạo biểu đồ');
            }
        }
    }
    
    if (typeof genderLabels !== 'undefined' && typeof genderData !== 'undefined') {
        const genderCtx = document.getElementById('genderChart');
        if (genderCtx) {
            try {
                window.genderChart = createPieChart(genderCtx, genderLabels, genderData, 'doughnut');
            } catch (error) {
                console.error('Error initializing gender chart:', error);
                displayNoDataMessage(genderCtx, 'Lỗi khi tạo biểu đồ');
            }
        }
    }
    
    // Initialize world map if available
    if (document.getElementById('world-map')) {
        initializeWorldMap();
    }
    
    // Setup micro interactions
    setupMicroInteractions();
}

/**
 * Create a pie or doughnut chart
 */
function createPieChart(ctx, labels, data, type = 'pie') {
    const colors = getThemeColors();
    
    // Create background and border colors arrays
    const backgroundColors = [
        'rgba(98, 74, 242, 0.8)',
        'rgba(49, 151, 149, 0.8)',
        'rgba(76, 201, 240, 0.8)',
        'rgba(247, 37, 133, 0.8)',
        'rgba(255, 159, 28, 0.8)',
        'rgba(16, 185, 129, 0.8)'
    ];
    
    const borderColors = [
        'rgba(98, 74, 242, 1)',
        'rgba(49, 151, 149, 1)',
        'rgba(76, 201, 240, 1)',
        'rgba(247, 37, 133, 1)',
        'rgba(255, 159, 28, 1)',
        'rgba(16, 185, 129, 1)'
    ];
    
    return new Chart(ctx, {
        type: type,
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: backgroundColors,
                borderColor: borderColors,
                borderWidth: 2,
                hoverOffset: 15
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                animateRotate: true,
                animateScale: true,
                duration: 1200,
                easing: 'easeOutQuart'
            },
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        color: colors.text,
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'rectRounded',
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    backgroundColor: colors.isDark ? '#2d3748' : 'rgba(255, 255, 255, 0.9)',
                    titleColor: colors.isDark ? '#fff' : '#1e293b',
                    bodyColor: colors.isDark ? '#e2e8f0' : '#4a5568',
                    borderColor: colors.isDark ? '#4a5568' : '#e2e8f0',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${percentage}% (${value})`;
                        }
                    }
                }
            }
        }
    });
}

/**
 * Initialize a world map for country distribution if jVectorMap is available
 */
function initializeWorldMap() {
    // This is just a placeholder, as we would need to include the jVectorMap library
    // The actual implementation would depend on the specific mapping library used
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.vectorMap !== 'undefined') {
        try {
            jQuery('#world-map').vectorMap({
                map: 'world_mill',
                backgroundColor: 'transparent',
                zoomOnScroll: false,
                series: {
                    regions: [{
                        values: countriesData || {},
                        scale: ['#C8EEFF', '#0071A4'],
                        normalizeFunction: 'polynomial'
                    }]
                },
                onRegionTipShow: function(e, el, code) {
                    el.html(el.html() + ' (Học viên: ' + (countriesData[code] || 0) + ')');
                }
            });
        } catch (error) {
            console.error('Error initializing world map:', error);
            const worldMap = document.getElementById('world-map');
            if (worldMap) {
                displayNoDataMessage(worldMap, 'Lỗi khi tạo bản đồ thế giới');
            }
        }
    }
}

/**
 * Display a no data message when chart data is empty
 */
function displayNoDataMessage(canvas, message) {
    // Create a container for the no data message
    const container = document.createElement('div');
    container.className = 'no-data';
    container.innerHTML = `
        <i class="fas fa-chart-bar"></i>
        <p>${message}</p>
    `;
    
    // Replace canvas with the no data message
    canvas.style.display = 'none';
    const parent = canvas.parentNode;
    if (parent) {
        parent.insertBefore(container, canvas);
    }
}

/**
 * Set up all event listeners for the page
 */
function setupEventListeners() {
    // Chart metric selector for trends chart
    const chartMetricSelector = document.getElementById('chart-metric');
    if (chartMetricSelector) {
        chartMetricSelector.addEventListener('change', function() {
            updateChartMetric(this.value);
        });
    }
    
    // Chart metric selector for monthly chart
    const monthlyChartMetricSelector = document.getElementById('monthly-chart-metric');
    if (monthlyChartMetricSelector) {
        monthlyChartMetricSelector.addEventListener('change', function() {
            updateMonthlyChartMetric(this.value);
        });
    }
    
    // Listen for theme changes to update charts
    document.addEventListener('themeChanged', function() {
        updateChartColors();
    });
    
    // Custom period button
    const customPeriodBtn = document.getElementById('custom-period');
    const dateRangeForm = document.getElementById('date-range-form');
    const cancelCustomDateBtn = document.getElementById('cancel-custom-date');
    
    if (customPeriodBtn && dateRangeForm) {
        customPeriodBtn.addEventListener('click', function(e) {
            e.preventDefault();
            dateRangeForm.style.display = 'block';
            
            // Smooth scroll to date range form
            dateRangeForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }
    
    if (cancelCustomDateBtn && dateRangeForm) {
        cancelCustomDateBtn.addEventListener('click', function() {
            dateRangeForm.style.display = 'none';
        });
    }
    
    // Make stat cards interactive
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        // Add a subtle animation on click
        card.addEventListener('click', function() {
            this.classList.add('stat-card-pulse');
            setTimeout(() => {
                this.classList.remove('stat-card-pulse');
            }, 500);
        });
    });
    
    // Make progress bars interactive
    document.querySelectorAll('.progress-bar-container').forEach(container => {
        container.addEventListener('mouseenter', function() {
            const progressBar = this.querySelector('.progress-bar');
            if (progressBar) {
                progressBar.style.opacity = '0.9';
                progressBar.style.boxShadow = '0 0 10px rgba(98, 74, 242, 0.5)';
            }
        });
        
        container.addEventListener('mouseleave', function() {
            const progressBar = this.querySelector('.progress-bar');
            if (progressBar) {
                progressBar.style.opacity = '1';
                progressBar.style.boxShadow = 'none';
            }
        });
    });
}

/**
 * Update the metric displayed on the trends chart
 */
function updateChartMetric(metric) {
    if (!window.trendsChart) return;
    
    const colors = getThemeColors();
    const chart = window.trendsChart;
    
    // Ensure data variables exist or use defaults
    const safeViewsData = typeof viewsData !== 'undefined' ? viewsData : [];
    const safeEnrollmentsData = typeof enrollmentsData !== 'undefined' ? enrollmentsData : [];
    const safeCompletionsData = typeof completionsData !== 'undefined' ? completionsData : [];
    
    let newData;
    let newLabel;
    let newColor;
    let newBorderColor;
    
    switch(metric) {
        case 'views':
            newData = safeViewsData;
            newLabel = 'Lượt Xem';
            newColor = colors.views;
            newBorderColor = colors.viewsBorder;
            break;
        case 'enrollments':
            newData = safeEnrollmentsData;
            newLabel = 'Đăng Ký Mới';
            newColor = colors.enrollments;
            newBorderColor = colors.enrollmentsBorder;
            break;
        case 'completions':
            newData = safeCompletionsData;
            newLabel = 'Hoàn Thành Bài Học';
            newColor = colors.completions;
            newBorderColor = colors.completionsBorder;
            break;
    }
    
    // Update chart data
    chart.data.datasets[0].data = newData;
    chart.data.datasets[0].label = newLabel;
    chart.data.datasets[0].backgroundColor = newColor;
    chart.data.datasets[0].borderColor = newBorderColor;
    
    // Update chart
    chart.update();
}

/**
 * Update the metric displayed on the monthly trends chart
 */
function updateMonthlyChartMetric(metric) {
    if (!window.monthlyTrendsChart) return;
    
    const colors = getThemeColors();
    const chart = window.monthlyTrendsChart;
    
    // Ensure monthly data variables exist or use defaults
    const safeMonthlyViewsData = typeof monthlyViewsData !== 'undefined' ? monthlyViewsData : [];
    const safeMonthlyEnrollmentsData = typeof monthlyEnrollmentsData !== 'undefined' ? monthlyEnrollmentsData : [];
    const safeMonthlyCompletionsData = typeof monthlyCompletionsData !== 'undefined' ? monthlyCompletionsData : [];
    
    let newData;
    let newLabel;
    let newColor;
    let newBorderColor;
    
    switch(metric) {
        case 'views':
            newData = safeMonthlyViewsData;
            newLabel = 'Lượt Xem Hàng Tháng';
            newColor = colors.views;
            newBorderColor = colors.viewsBorder;
            break;
        case 'enrollments':
            newData = safeMonthlyEnrollmentsData;
            newLabel = 'Đăng Ký Mới Hàng Tháng';
            newColor = colors.enrollments;
            newBorderColor = colors.enrollmentsBorder;
            break;
        case 'completions':
            newData = safeMonthlyCompletionsData;
            newLabel = 'Hoàn Thành Bài Học Hàng Tháng';
            newColor = colors.completions;
            newBorderColor = colors.completionsBorder;
            break;
    }
    
    // Update chart data
    chart.data.datasets[0].data = newData;
    chart.data.datasets[0].label = newLabel;
    chart.data.datasets[0].backgroundColor = newColor;
    chart.data.datasets[0].borderColor = newBorderColor;
    
    // Update chart
    chart.update();
}

/**
 * Update all chart colors based on current theme
 */
function updateChartColors() {
    const colors = getThemeColors();
    
    // Update all Chart.js instances
    if (typeof Chart !== 'undefined' && Chart.instances) {
        Object.values(Chart.instances).forEach(chart => {
            // Update scales colors if present
            if (chart.options && chart.options.scales) {
                if (chart.options.scales.x) {
                    chart.options.scales.x.grid.color = colors.grid;
                    chart.options.scales.x.ticks.color = colors.text;
                }
                if (chart.options.scales.y) {
                    chart.options.scales.y.grid.color = colors.grid;
                    chart.options.scales.y.ticks.color = colors.text;
                }
            }
            
            // Update tooltip colors
            if (chart.options && chart.options.plugins && chart.options.plugins.tooltip) {
                chart.options.plugins.tooltip.backgroundColor = colors.isDark ? '#2d3748' : 'rgba(255, 255, 255, 0.9)';
                chart.options.plugins.tooltip.titleColor = colors.isDark ? '#fff' : '#1e293b';
                chart.options.plugins.tooltip.bodyColor = colors.isDark ? '#e2e8f0' : '#4a5568';
                chart.options.plugins.tooltip.borderColor = colors.isDark ? '#4a5568' : '#e2e8f0';
            }
            
            // Update legend text color
            if (chart.options && chart.options.plugins && chart.options.plugins.legend) {
                chart.options.plugins.legend.labels.color = colors.text;
            }
            
            // Update dataset colors based on chart type and label
            if (chart.data && chart.data.datasets) {
                chart.data.datasets.forEach(dataset => {
                    const label = dataset.label || '';
                    
                    if (chart.config.type === 'line' || chart.config.type === 'bar') {
                        if (label.includes('Lượt Xem')) {
                            dataset.backgroundColor = colors.views;
                            dataset.borderColor = colors.viewsBorder;
                        } else if (label.includes('Đăng Ký') || label.includes('Ghi Danh')) {
                            dataset.backgroundColor = colors.enrollments;
                            dataset.borderColor = colors.enrollmentsBorder;
                        } else if (label.includes('Hoàn Thành')) {
                            dataset.backgroundColor = colors.completions;
                            dataset.borderColor = colors.completionsBorder;
                        }
                    }
                });
            }
            
            try {
                chart.update();
            } catch (error) {
                console.error('Error updating chart:', error);
            }
        });
    }
    
    // Update vector map if available
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.vectorMap !== 'undefined' && jQuery('#world-map').length) {
        try {
            const worldMap = jQuery('#world-map').vectorMap('get', 'mapObject');
            if (worldMap) {
                // Update map colors based on theme
                worldMap.series.regions[0].setScale([
                    colors.isDark ? '#2a4858' : '#C8EEFF', 
                    colors.isDark ? '#6abce2' : '#0071A4'
                ]);
                worldMap.series.regions[0].params.min = Math.min(...Object.values(countriesData || {}));
                worldMap.series.regions[0].params.max = Math.max(...Object.values(countriesData || {}));
                worldMap.series.regions[0].setValues(countriesData || {});
            }
        } catch (error) {
            console.error('Error updating vector map:', error);
        }
    }
}

/**
 * Get theme colors based on current theme
 */
function getThemeColors() {
    const isDark = document.body.classList.contains('dark-mode');
    
    return {
        isDark: isDark,
        text: isDark ? '#e2e8f0' : '#1e293b',
        grid: isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)',
        views: isDark ? 'rgba(98, 74, 242, 0.7)' : 'rgba(98, 74, 242, 0.5)',
        viewsBorder: 'rgba(98, 74, 242, 1)',
        enrollments: isDark ? 'rgba(49, 151, 149, 0.7)' : 'rgba(49, 151, 149, 0.5)',
        enrollmentsBorder: 'rgba(49, 151, 149, 1)',
        completions: isDark ? 'rgba(16, 185, 129, 0.7)' : 'rgba(16, 185, 129, 0.5)',
        completionsBorder: 'rgba(16, 185, 129, 1)'
    };
}

/**
 * Setup micro-interactions for analytics elements
 */
function setupMicroInteractions() {
    // Progress bar hover effects - applied in setupEventListeners now
    
    // Chart card hover effects
    document.querySelectorAll('.chart-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 12px 20px rgba(0,0,0,0.1)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
            this.style.boxShadow = '';
        });
    });
    
    // Add stylesheet for dynamic animations
    const style = document.createElement('style');
    style.textContent = `
        .chart-updating {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            z-index: 10;
        }
        
        .dark-mode .chart-updating {
            background: rgba(0,0,0,0.5);
        }
        
        .chart-updating i {
            font-size: 2rem;
            color: var(--primary-color);
        }
        
        .fade-out {
            animation: fadeOut 0.3s forwards;
        }
        
        @keyframes fadeOut {
            to { opacity: 0; }
        }
        
        .stat-card-pulse {
            animation: pulse 0.5s;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.03); }
            100% { transform: scale(1); }
        }
    `;
    document.head.appendChild(style);
}

// Lesson table functionality
function initLessonTable() {
    // Variables for lesson table
    const lessonTable = document.querySelector('.lesson-progress-table');
    if (!lessonTable) return;
    
    const lessonRows = Array.from(lessonTable.querySelectorAll('tbody tr'));
    const lessonSearch = document.getElementById('lesson-search');
    const sortHeaders = document.querySelectorAll('.sort-header');
    const rowsPerPage = 10;
    let currentPage = 1;
    let filteredRows = [...lessonRows];
    
    // Set up pagination if there are enough rows
    function setupPagination() {
        const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        const pageNumbers = document.getElementById('page-numbers');
        const prevBtn = document.getElementById('prev-page');
        const nextBtn = document.getElementById('next-page');
        const showingRecords = document.getElementById('showing-records');
        const totalRecords = document.getElementById('total-records');
        
        if (!pageNumbers || !prevBtn || !nextBtn) return;
        
        // Update total records count
        if (totalRecords) {
            totalRecords.textContent = filteredRows.length;
        }
        
        // Clear existing page numbers
        pageNumbers.innerHTML = '';
        
        // Add page numbers (max 5 visible)
        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
        
        // Adjust start page if needed
        if (endPage - startPage + 1 < maxVisiblePages) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        // Add "first page" indicator if needed
        if (startPage > 1) {
            const firstPage = document.createElement('span');
            firstPage.className = 'page-number';
            firstPage.textContent = '1';
            firstPage.addEventListener('click', () => goToPage(1));
            pageNumbers.appendChild(firstPage);
            
            if (startPage > 2) {
                const ellipsis = document.createElement('span');
                ellipsis.className = 'page-ellipsis';
                ellipsis.textContent = '...';
                pageNumbers.appendChild(ellipsis);
            }
        }
        
        // Add page numbers
        for (let i = startPage; i <= endPage; i++) {
            const pageNumber = document.createElement('span');
            pageNumber.className = 'page-number' + (i === currentPage ? ' active' : '');
            pageNumber.textContent = i;
            pageNumber.addEventListener('click', () => goToPage(i));
            pageNumbers.appendChild(pageNumber);
        }
        
        // Add "last page" indicator if needed
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                const ellipsis = document.createElement('span');
                ellipsis.className = 'page-ellipsis';
                ellipsis.textContent = '...';
                pageNumbers.appendChild(ellipsis);
            }
            
            const lastPage = document.createElement('span');
            lastPage.className = 'page-number';
            lastPage.textContent = totalPages;
            lastPage.addEventListener('click', () => goToPage(totalPages));
            pageNumbers.appendChild(lastPage);
        }
        
        // Update pagination buttons state
        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage === totalPages;
        
        // Add event listeners for prev/next buttons
        prevBtn.onclick = function() {
            if (currentPage > 1) goToPage(currentPage - 1);
        };
        
        nextBtn.onclick = function() {
            if (currentPage < totalPages) goToPage(currentPage + 1);
        };
        
        // Update showing records text
        const start = (currentPage - 1) * rowsPerPage + 1;
        const end = Math.min(currentPage * rowsPerPage, filteredRows.length);
        if (showingRecords) {
            showingRecords.textContent = `${start}-${end}`;
        }
    }
    
    // Go to specific page
    function goToPage(page) {
        currentPage = page;
        renderTable();
        setupPagination();
    }
    
    // Render the table with filtered and sorted rows
    function renderTable() {
        const tableBody = lessonTable.querySelector('tbody');
        const start = (currentPage - 1) * rowsPerPage;
        const end = Math.min(start + rowsPerPage, filteredRows.length);
        const visibleRows = filteredRows.slice(start, end);
        
        // Hide all rows
        lessonRows.forEach(row => row.style.display = 'none');
        
        // Show only visible rows
        visibleRows.forEach(row => row.style.display = '');
    }
    
    // Sort the table by column
    function sortTable(column, direction) {
        filteredRows.sort((a, b) => {
            let valA, valB;
            
            switch(column) {
                case 'views':
                    valA = parseInt(a.cells[1].textContent.replace(/,/g, ''));
                    valB = parseInt(b.cells[1].textContent.replace(/,/g, ''));
                    break;
                case 'completions':
                    valA = parseInt(a.cells[2].textContent.replace(/,/g, ''));
                    valB = parseInt(b.cells[2].textContent.replace(/,/g, ''));
                    break;
                case 'rate':
                    valA = parseInt(a.cells[3].querySelector('.completion-badge').textContent);
                    valB = parseInt(b.cells[3].querySelector('.completion-badge').textContent);
                    break;
                case 'progress':
                    valA = parseInt(a.cells[4].querySelector('.progress-label').textContent);
                    valB = parseInt(b.cells[4].querySelector('.progress-label').textContent);
                    break;
                default:
                    // Default to title
                    valA = a.cells[0].querySelector('strong').textContent.trim().toLowerCase();
                    valB = b.cells[0].querySelector('strong').textContent.trim().toLowerCase();
                    return direction === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
            }
            
            return direction === 'asc' ? valA - valB : valB - valA;
        });
        
        // Reset to first page
        currentPage = 1;
        renderTable();
        setupPagination();
    }
    
    // Search functionality
    function searchLessons(query) {
        query = query.toLowerCase().trim();
        
        if (query === '') {
            filteredRows = [...lessonRows];
        } else {
            filteredRows = lessonRows.filter(row => {
                const lessonTitle = row.cells[0].querySelector('strong').textContent.toLowerCase();
                return lessonTitle.includes(query);
            });
        }
        
        // Reset to first page
        currentPage = 1;
        renderTable();
        setupPagination();
    }
    
    // Set up search
    if (lessonSearch) {
        lessonSearch.addEventListener('input', function() {
            searchLessons(this.value);
        });
    }
    
    // Set up sort headers
    sortHeaders.forEach(header => {
        header.addEventListener('click', function() {
            // Get current sort direction
            const column = this.getAttribute('data-sort');
            let direction = 'desc';
            
            // Toggle direction if already sorted
            if (this.classList.contains('desc')) {
                direction = 'asc';
                this.classList.remove('desc');
                this.classList.add('asc');
            } else if (this.classList.contains('asc')) {
                direction = 'desc';
                this.classList.remove('asc');
                this.classList.add('desc');
            } else {
                // New sort, default to desc
                this.classList.add('desc');
            }
            
            // Remove sort classes from other headers
            sortHeaders.forEach(h => {
                if (h !== this) {
                    h.classList.remove('asc', 'desc');
                }
            });
            
            // Sort the table
            sortTable(column, direction);
        });
    });
    
    // Initialize the table
    if (lessonRows.length > 0) {
        // Set up initial sort and pagination
        const lessonSort = document.getElementById('lesson-sort');
        if (lessonSort) {
            lessonSort.addEventListener('change', function() {
                const sortValue = this.value;
                switch (sortValue) {
                    case 'views':
                        sortTable('views', 'desc');
                        break;
                    case 'completion':
                        sortTable('rate', 'desc');
                        break;
                    case 'progress':
                        sortTable('progress', 'desc');
                        break;
                    default:
                        // Default to title ascending for order
                        sortTable('title', 'asc');
                }
            });
        }
        
        // Initial render
        setupPagination();
        renderTable();
    }
}

/**
 * Initialize tab functionality
 */
function initializeTabs() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Remove active class from all buttons and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to clicked button
            button.classList.add('active');
            
            // Show corresponding content
            const tabId = button.getAttribute('data-tab') + '-tab';
            const tabContent = document.getElementById(tabId);
            
            if (tabContent) {
                tabContent.classList.add('active');
                
                // Re-animate all items in this tab
                const animatableItems = tabContent.querySelectorAll('.animated-fade-in, .animated-fade-in-left, .animated-fade-in-right');
                animatableItems.forEach(item => {
                    item.style.animation = 'none';
                    void item.offsetWidth; // Trigger reflow
                    item.style.animation = null;
                });
            }
        });
    });
}

/**
 * Initialize export dropdown functionality
 */
function initializeExport() {
    const exportBtn = document.getElementById('export-options-btn');
    const exportDropdown = document.getElementById('export-dropdown');
    
    if (exportBtn && exportDropdown) {
        exportBtn.addEventListener('click', function(e) {
            e.preventDefault();
            exportDropdown.style.display = exportDropdown.style.display === 'block' ? 'none' : 'block';
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!exportBtn.contains(e.target) && !exportDropdown.contains(e.target)) {
                exportDropdown.style.display = 'none';
            }
        });
        
        // Export handlers
        document.getElementById('export-pdf')?.addEventListener('click', exportPDF);
        document.getElementById('export-csv')?.addEventListener('click', exportCSV);
        document.getElementById('export-excel')?.addEventListener('click', exportExcel);
        document.getElementById('export-print')?.addEventListener('click', printReport);
    }
}

/**
 * Initialize share functionality
 */
function initializeShare() {
    const shareBtn = document.getElementById('share-report');
    const shareModal = document.getElementById('share-modal');
    const closeBtn = document.getElementById('share-modal-close');
    const copyBtn = document.getElementById('copy-url-btn');
    const sendEmailBtn = document.getElementById('send-email-btn');
    const socialButtons = document.querySelectorAll('.social-btn');
    
    if (shareBtn && shareModal) {
        shareBtn.addEventListener('click', function(e) {
            e.preventDefault();
            shareModal.style.display = 'flex';
        });
        
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                shareModal.style.display = 'none';
            });
        }
        
        // Close modal when clicking outside of modal content
        shareModal.addEventListener('click', function(e) {
            if (e.target === shareModal) {
                shareModal.style.display = 'none';
            }
        });
        
        // Copy URL functionality
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                const urlInput = document.getElementById('report-url');
                if (urlInput) {
                    urlInput.select();
                    document.execCommand('copy');
                    showToast('URL copied to clipboard!', 'success');
                }
            });
        }
        
        // Send email functionality
        if (sendEmailBtn) {
            sendEmailBtn.addEventListener('click', function() {
                const emailInput = document.getElementById('share-email');
                if (emailInput && emailInput.value) {
                    // Here you would normally make an AJAX call to send the email
                    // For demonstration, we'll just show a success toast
                    showToast('Report shared via email!', 'success');
                    emailInput.value = '';
                } else {
                    showToast('Please enter a valid email address', 'error');
                }
            });
        }
        
        // Social sharing functionality
        socialButtons.forEach(button => {
            button.addEventListener('click', function() {
                const platform = this.classList.contains('facebook') ? 'Facebook' :
                                this.classList.contains('twitter') ? 'Twitter' :
                                this.classList.contains('linkedin') ? 'LinkedIn' : '';
                
                // Here you would integrate with social APIs
                // For demonstration, we'll just show a toast
                showToast(`Report shared on ${platform}!`, 'success');
            });
        });
    }
}

/**
 * Show a toast notification
 * @param {string} message - The message to display
 * @param {string} type - The type of toast (success, error, info, warning)
 * @param {number} duration - How long to show the toast (ms)
 */
function showToast(message, type = 'info', duration = 3000) {
    const toastContainer = document.getElementById('toast-container');
    
    if (!toastContainer) return;
    
    const toast = document.createElement('div');
    toast.classList.add('toast', `toast-${type}`);
    
    let icon = '';
    switch (type) {
        case 'success':
            icon = '<i class="fas fa-check-circle toast-icon"></i>';
            break;
        case 'error':
            icon = '<i class="fas fa-exclamation-circle toast-icon"></i>';
            break;
        case 'warning':
            icon = '<i class="fas fa-exclamation-triangle toast-icon"></i>';
            break;
        default:
            icon = '<i class="fas fa-info-circle toast-icon"></i>';
    }
    
    toast.innerHTML = `
        ${icon}
        <div class="toast-content">
            <div class="toast-title">${type.charAt(0).toUpperCase() + type.slice(1)}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close"><i class="fas fa-times"></i></button>
    `;
    
    toastContainer.appendChild(toast);
    
    // Add close functionality
    toast.querySelector('.toast-close').addEventListener('click', function() {
        toast.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => {
            toast.remove();
        }, 300);
    });
    
    // Auto remove after duration
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => {
                if (toast.parentNode) toast.remove();
            }, 300);
        }
    }, duration);
}

/**
 * Refresh data via AJAX
 */
function refreshData() {
    showLoading();
    
    // Get the current URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const courseId = urlParams.get('course_id');
    const period = urlParams.get('period') || 'month';
    const startDate = urlParams.get('start_date') || '';
    const endDate = urlParams.get('end_date') || '';
    
    // Create API URL
    // Use absolute URL
    const protocol = window.location.protocol;
    const host = window.location.host;
    const apiUrl = `${protocol}//${host}/api/analytics/refresh-data.php`;
    
    fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            course_id: courseId,
            period: period,
            start_date: startDate,
            end_date: endDate
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        hideLoading();
        
        if (data.success) {
            // Update the charts and stats with new data
            updateChartsWithNewData(data);
            updateLastUpdated(data.last_updated);
            
            showToast('Data refreshed successfully!', 'success');
        } else {
            showToast(data.message || 'Failed to refresh data', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showToast('Error refreshing data. Please try again.', 'error');
    });
}

/**
 * Update charts with new data
 * @param {Object} data - The new data
 */
function updateChartsWithNewData(data) {
    if (data.chart_data) {
        if (data.chart_data.date_labels) {
            window.dateLabels = data.chart_data.date_labels;
        }
        
        if (data.chart_data.views_data) {
            window.viewsData = data.chart_data.views_data;
        }
        
        if (data.chart_data.enrollments_data) {
            window.enrollmentsData = data.chart_data.enrollments_data;
        }
        
        if (data.chart_data.completions_data) {
            window.completionsData = data.chart_data.completions_data;
        }
        
        // Update charts
        updateTrendsChart();
        updateMonthlyTrendsChart();
    }
    
    // Update stats cards
    if (data.daily_stats) {
        updateStatsCards(data.daily_stats);
    }
    
    // Update lesson data if available
    if (data.lesson_stats) {
        updateLessonTable(data.lesson_stats);
    }
}

/**
 * Update the last updated timestamp
 * @param {string} timestamp - The new timestamp
 */
function updateLastUpdated(timestamp) {
    const lastUpdatedElement = document.querySelector('.last-updated-info strong');
    if (lastUpdatedElement && timestamp) {
        lastUpdatedElement.textContent = timestamp;
    }
}

/**
 * Update stats cards with new data
 * @param {Array} statsData - Array of stats objects
 */
function updateStatsCards(statsData) {
    const statCards = document.querySelectorAll('.stat-card');
    
    statsData.forEach((stat, index) => {
        if (index < statCards.length) {
            const valueElement = statCards[index].querySelector('p');
            const trendElement = statCards[index].querySelector('.stat-trend');
            
            if (valueElement) {
                valueElement.textContent = Number(stat.value).toLocaleString();
            }
            
            if (trendElement && stat.trend && stat.trend_percentage !== undefined) {
                trendElement.className = `stat-trend ${stat.trend}`;
                trendElement.innerHTML = `
                    <i class="fas fa-${stat.trend === 'up' ? 'arrow-up' : 'arrow-down'}"></i>
                    <small>${Math.abs(stat.trend_percentage)}% từ kỳ trước</small>
                `;
            }
        }
    });
}

/**
 * Update lesson table with new data
 * @param {Array} lessonStats - Array of lesson stats
 */
function updateLessonTable(lessonStats) {
    const tbody = document.querySelector('.lesson-progress-table tbody');
    
    if (!tbody) return;
    
    // Clear existing rows
    tbody.innerHTML = '';
    
    if (lessonStats.length === 0) {
        const row = document.createElement('tr');
        row.innerHTML = '<td colspan="5" class="text-center">Chưa có dữ liệu về tiến độ bài học</td>';
        tbody.appendChild(row);
        return;
    }
    
    // Add new rows
    lessonStats.forEach(lesson => {
        const row = document.createElement('tr');
        row.classList.add('lesson-row');
        
        let completionRate = 0;
        if ((lesson.total_viewers || 0) > 0) {
            completionRate = Math.round((lesson.completions || 0) / lesson.total_viewers * 100);
        }
        
        let completionClass = 'low';
        if (completionRate >= 70) {
            completionClass = 'high';
        } else if (completionRate >= 30) {
            completionClass = 'medium';
        }
        
        let iconClass = 'times-circle text-danger';
        if (completionRate >= 70) {
            iconClass = 'check-circle text-success';
        } else if (completionRate >= 30) {
            iconClass = 'adjust text-warning';
        }
        
        row.innerHTML = `
            <td>
                <div class="lesson-title">
                    <span class="lesson-icon">
                        <i class="fas fa-${iconClass}"></i>
                    </span>
                    <strong>${lesson.title}</strong>
                </div>
            </td>
            <td class="text-center">
                <span class="stat-value">${Number(lesson.total_viewers || 0).toLocaleString()}</span>
            </td>
            <td class="text-center">
                <span class="stat-value">${Number(lesson.completions || 0).toLocaleString()}</span>
            </td>
            <td class="text-center">
                <span class="completion-badge completion-${completionClass}">
                    ${completionRate}%
                </span>
            </td>
            <td>
                <div class="progress-bar-container">
                    <div class="progress-bar" style="width: ${Math.round(lesson.avg_progress || 0)}%"></div>
                    <span class="progress-label">${Math.round(lesson.avg_progress || 0)}%</span>
                </div>
            </td>
        `;
        
        tbody.appendChild(row);
    });
    
    // Reinitialize lesson table functionality
    initializeLessonTable();
}

/**
 * Export functions
 */
function exportPDF() {
    showLoading();
    
    // Get the current URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const courseId = urlParams.get('course_id');
    const period = urlParams.get('period') || 'month';
    const startDate = urlParams.get('start_date') || '';
    const endDate = urlParams.get('end_date') || '';
    
    // Create absolute URL for the export
    const protocol = window.location.protocol;
    const host = window.location.host;
    const exportUrl = `${protocol}//${host}/api/export/export-pdf.php?course_id=${courseId}&period=${period}&start_date=${startDate}&end_date=${endDate}`;
    
    // Create a hidden iframe to download the file
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = exportUrl;
    document.body.appendChild(iframe);
    
    // Show success message after a delay
    setTimeout(() => {
        hideLoading();
        showToast('PDF exported successfully!', 'success');
        document.body.removeChild(iframe);
    }, 1500);
}

function exportCSV() {
    showLoading();
    
    // Get the current URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const courseId = urlParams.get('course_id');
    const period = urlParams.get('period') || 'month';
    const startDate = urlParams.get('start_date') || '';
    const endDate = urlParams.get('end_date') || '';
    
    // Create absolute URL for the export
    const protocol = window.location.protocol;
    const host = window.location.host;
    const exportUrl = `${protocol}//${host}/api/export/export-csv.php?course_id=${courseId}&period=${period}&start_date=${startDate}&end_date=${endDate}`;
    
    // Create a hidden iframe to download the file
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = exportUrl;
    document.body.appendChild(iframe);
    
    // Show success message after a delay
    setTimeout(() => {
        hideLoading();
        showToast('CSV exported successfully!', 'success');
        document.body.removeChild(iframe);
    }, 1500);
}

function exportExcel() {
    showLoading();
    
    // Get the current URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const courseId = urlParams.get('course_id');
    const period = urlParams.get('period') || 'month';
    const startDate = urlParams.get('start_date') || '';
    const endDate = urlParams.get('end_date') || '';
    
    // Create absolute URL for the export
    const protocol = window.location.protocol;
    const host = window.location.host;
    const exportUrl = `${protocol}//${host}/api/export/export-excel.php?course_id=${courseId}&period=${period}&start_date=${startDate}&end_date=${endDate}`;
    
    // Create a hidden iframe to download the file
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = exportUrl;
    document.body.appendChild(iframe);
    
    // Show success message after a delay
    setTimeout(() => {
        hideLoading();
        showToast('Excel file exported successfully!', 'success');
        document.body.removeChild(iframe);
    }, 1500);
}

function printReport() {
    showToast('Preparing report for printing...', 'info');
    window.print();
}

/**
 * Show loading indicator
 */
function showLoading() {
    isLoading = true;
    
    const refreshBtn = document.getElementById('refresh-data');
    if (refreshBtn) {
        refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span class="refresh-text">Đang tải...</span>';
        refreshBtn.disabled = true;
    }
    
    // Create overlay if it doesn't exist
    let loadingOverlay = document.querySelector('.loading-overlay');
    if (!loadingOverlay) {
        loadingOverlay = document.createElement('div');
        loadingOverlay.className = 'loading-overlay';
        loadingOverlay.innerHTML = `
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Đang tải dữ liệu...</p>
            </div>
        `;
        document.body.appendChild(loadingOverlay);
    } else {
        loadingOverlay.style.display = 'flex';
    }
}

/**
 * Hide loading indicator
 */
function hideLoading() {
    isLoading = false;
    
    const refreshBtn = document.getElementById('refresh-data');
    if (refreshBtn) {
        refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> <span class="refresh-text">Làm mới</span>';
        refreshBtn.disabled = false;
    }
    
    // Hide overlay
    const loadingOverlay = document.querySelector('.loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.style.display = 'none';
    }
}

// ... keep the existing chart functions
// ... existing code ... 