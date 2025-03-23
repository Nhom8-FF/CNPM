/**
 * Instructor Dashboard JavaScript
 * Handles all client-side logic for the instructor dashboard
 */

document.addEventListener('DOMContentLoaded', function() {
    // Toggle mobile menu
    const mobileToggle = document.getElementById('mobile-toggle');
    const sidebar = document.getElementById('sidebar');
    
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    // Toggle dark mode
    const modeToggle = document.getElementById('mode-toggle');
    
    if (modeToggle) {
        modeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            localStorage.setItem('darkMode', isDark);
            
            // Update toggle icon
            this.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            
            // Fire theme changed event
            document.dispatchEvent(new CustomEvent('themeChanged'));
        });
    }
    
    // Check saved theme preference
    if (localStorage.getItem('darkMode') === 'true') {
        document.body.classList.add('dark-mode');
        if (modeToggle) {
            modeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        }
    }
    
    // Initialize charts
    initializeCharts();
    
    // Set up tab functionality
    setupTabs();
});

/**
 * Get theme colors based on current theme
 */
function getThemeColors() {
    const isDark = document.body.classList.contains('dark-mode');
    
    return {
        text: isDark ? '#e2e8f0' : '#1e293b',
        grid: isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)',
        views: isDark ? 'rgba(98, 74, 242, 0.6)' : 'rgba(98, 74, 242, 0.5)',
        viewsBorder: 'rgba(98, 74, 242, 1)',
        enrollments: isDark ? 'rgba(49, 151, 149, 0.6)' : 'rgba(49, 151, 149, 0.5)',
        enrollmentsBorder: 'rgba(49, 151, 149, 1)',
        completions: isDark ? 'rgba(16, 185, 129, 0.6)' : 'rgba(16, 185, 129, 0.5)',
        completionsBorder: 'rgba(16, 185, 129, 1)'
    };
}

/**
 * Initialize dashboard charts
 */
function initializeCharts() {
    if (typeof Chart === 'undefined') return;
    
    const colors = getThemeColors();
    
    // Create default enrollment data if not defined
    const defaultEnrollmentData = {
        enrollments: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        income: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
    };
    
    // Use enrollmentData if defined, otherwise use default data
    const enrollData = typeof enrollmentData !== 'undefined' ? enrollmentData : defaultEnrollmentData;
    
    // Enrollment Chart
    const enrollmentCtx = document.getElementById('enrollmentChart');
    if (enrollmentCtx) {
        new Chart(enrollmentCtx, {
            type: 'line',
            data: {
                labels: ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9', 'T10', 'T11', 'T12'],
                datasets: [{
                    label: 'Ghi Danh',
                    data: enrollData.enrollments,
                    backgroundColor: colors.enrollments,
                    borderColor: colors.enrollmentsBorder,
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            color: colors.grid
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
                            color: colors.text
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: colors.text
                        }
                    }
                }
            }
        });
    }
    
    // Income Chart
    const incomeCtx = document.getElementById('incomeChart');
    if (incomeCtx) {
        new Chart(incomeCtx, {
            type: 'bar',
            data: {
                labels: ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9', 'T10', 'T11', 'T12'],
                datasets: [{
                    label: 'Thu Nhập (VNĐ)',
                    data: enrollData.income,
                    backgroundColor: colors.views,
                    borderColor: colors.viewsBorder,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            color: colors.grid
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
                            color: colors.text
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: colors.text
                        }
                    }
                }
            }
        });
    }
    
    // Listen for theme changes to update charts
    document.addEventListener('themeChanged', function() {
        updateChartColors();
    });
}

/**
 * Update chart colors based on theme
 */
function updateChartColors() {
    const colors = getThemeColors();
    
    // Update all chart instances
    Chart.instances.forEach(chart => {
        // Update grid and text colors
        if (chart.options.scales.x) {
            chart.options.scales.x.grid.color = colors.grid;
            chart.options.scales.x.ticks.color = colors.text;
            chart.options.scales.y.grid.color = colors.grid;
            chart.options.scales.y.ticks.color = colors.text;
        }
        
        // Update legend text color
        if (chart.options.plugins.legend) {
            chart.options.plugins.legend.labels.color = colors.text;
        }
        
        // Update dataset colors based on chart type and label
        chart.data.datasets.forEach(dataset => {
            if (dataset.label.includes("Ghi Danh")) {
                dataset.backgroundColor = colors.enrollments;
                dataset.borderColor = colors.enrollmentsBorder;
            } else if (dataset.label.includes("Thu Nhập")) {
                dataset.backgroundColor = colors.views;
                dataset.borderColor = colors.viewsBorder;
            } else if (dataset.label.includes("Lượt Xem")) {
                dataset.backgroundColor = colors.views;
                dataset.borderColor = colors.viewsBorder;
            } else if (dataset.label.includes("Đăng Ký")) {
                dataset.backgroundColor = colors.enrollments;
                dataset.borderColor = colors.enrollmentsBorder;
            }
        });
        
        chart.update();
    });
}

/**
 * Set up tab functionality
 */
function setupTabs() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Remove active class from all buttons and content
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Add active class to clicked button
            button.classList.add('active');
            
            // Show corresponding content
            const tabName = button.getAttribute('data-tab');
            document.getElementById(`${tabName}-tab`).classList.add('active');
        });
    });
}

// Mobile menu toggle
const mobileToggle = document.getElementById("mobile-toggle");
const sidebar = document.getElementById("sidebar");

mobileToggle.addEventListener("click", () => {
  sidebar.classList.toggle("active");
});

// Function to get current theme colors
function getThemeColors() {
  const isDark = document.body.classList.contains("dark-mode");

  return {
    textColor: isDark ? "#f8f9fa" : "#212529",
    gridColor: isDark ? "rgba(255, 255, 255, 0.1)" : "rgba(0, 0, 0, 0.1)",
    primaryColor: "#624af2",
    secondaryColor: "rgba(98, 74, 242, 0.2)",
  };
}

// Initialize charts
let enrollmentChart, incomeChart;

function initCharts() {
  // Create default data if enrollmentData is not defined
  const defaultData = {
    enrollments: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
    income: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
  };
  
  // Use enrollmentData if it exists, otherwise use default data
  const data = typeof enrollmentData !== 'undefined' ? enrollmentData : defaultData;

  const colors = getThemeColors();
  const months = [
    "Jan",
    "Feb",
    "Mar",
    "Apr",
    "May",
    "Jun",
    "Jul",
    "Aug",
    "Sep",
    "Oct",
    "Nov",
    "Dec",
  ];

  // Enrollment Chart
  const enrollmentCtx = document.getElementById("enrollmentChart");
  if (enrollmentCtx) {
    enrollmentChart = new Chart(enrollmentCtx.getContext("2d"), {
      type: "line",
      data: {
        labels: months,
        datasets: [
          {
            label: "Ghi Danh",
            data: data.enrollments,
            borderColor: colors.primaryColor,
            backgroundColor: colors.secondaryColor,
            tension: 0.4,
            fill: true,
            pointBackgroundColor: colors.primaryColor,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              color: colors.gridColor,
            },
            ticks: {
              color: colors.textColor,
            },
          },
          x: {
            grid: {
              display: false,
            },
            ticks: {
              color: colors.textColor,
            },
          },
        },
      },
    });
  }

  // Income Chart
  const incomeCtx = document.getElementById("incomeChart");
  if (incomeCtx) {
    incomeChart = new Chart(incomeCtx.getContext("2d"), {
      type: "bar",
      data: {
        labels: months,
        datasets: [
          {
            label: "Thu Nhập (VNĐ)",
            data: data.income,
            backgroundColor: colors.primaryColor,
            borderRadius: 5,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              color: colors.gridColor,
            },
            ticks: {
              color: colors.textColor,
              callback: function (value) {
                if (value >= 1000000) {
                  return value / 1000000 + "tr";
                }
                return value;
              },
            },
          },
          x: {
            grid: {
              display: false,
            },
            ticks: {
              color: colors.textColor,
            },
          },
        },
      },
    });
  }
}

// Update chart colors when theme changes
function updateChartColors() {
  if (!enrollmentChart || !incomeChart) return;

  const colors = getThemeColors();

  // Update enrollment chart
  enrollmentChart.options.scales.y.grid.color = colors.gridColor;
  enrollmentChart.options.scales.y.ticks.color = colors.textColor;
  enrollmentChart.options.scales.x.ticks.color = colors.textColor;

  // Update income chart
  incomeChart.options.scales.y.grid.color = colors.gridColor;
  incomeChart.options.scales.y.ticks.color = colors.textColor;
  incomeChart.options.scales.x.ticks.color = colors.textColor;

  // Update the charts
  enrollmentChart.update();
  incomeChart.update();
}

// Initialize charts when the DOM is loaded
document.addEventListener("DOMContentLoaded", initCharts);

document.addEventListener("DOMContentLoaded", function () {
  // Initialize Chart.js charts if available
  initializeCharts();

  // Setup micro-interactions
  setupMicroInteractions();

  // Handle assignments and assessments tabs
  const tabButtons = document.querySelectorAll(".tab-btn");
  const tabContents = document.querySelectorAll(".tab-content");

  tabButtons.forEach((button) => {
    button.addEventListener("click", () => {
      // Remove active class from all buttons and contents
      tabButtons.forEach((btn) => btn.classList.remove("active"));
      tabContents.forEach((content) => content.classList.remove("active"));

      // Add active class to current button
      button.classList.add("active");

      // Show corresponding content
      const tabId = button.getAttribute("data-tab");
      document.getElementById(`${tabId}-tab`).classList.add("active");
    });
  });

  // Handle sidebar toggle
  const sidebarToggle = document.querySelector(".sidebar-toggle");
  const sidebar = document.querySelector(".sidebar");
  const mainContent = document.querySelector(".main-content");

  if (sidebarToggle && sidebar && mainContent) {
    sidebarToggle.addEventListener("click", function () {
      sidebar.classList.toggle("collapsed");
      mainContent.classList.toggle("expanded");

      // Add animation with GSAP if available
      if (typeof gsap !== "undefined") {
        if (sidebar.classList.contains("collapsed")) {
          gsap.to(sidebar, {
            width: "70px",
            duration: 0.3,
            ease: "power2.out",
          });
          gsap.to(mainContent, {
            marginLeft: "90px",
            duration: 0.3,
            ease: "power2.out",
          });
        } else {
          gsap.to(sidebar, {
            width: "250px",
            duration: 0.3,
            ease: "power2.out",
          });
          gsap.to(mainContent, {
            marginLeft: "270px",
            duration: 0.3,
            ease: "power2.out",
          });
        }
      }
    });
  }

  // Initialize tooltips
  const tooltips = document.querySelectorAll("[data-tooltip]");
  tooltips.forEach((tooltip) => {
    tooltip.addEventListener("mouseenter", function () {
      const text = this.getAttribute("data-tooltip");
      const tooltipEl = document.createElement("div");
      tooltipEl.className = "tooltip";
      tooltipEl.textContent = text;
      document.body.appendChild(tooltipEl);

      const rect = this.getBoundingClientRect();
      tooltipEl.style.top = rect.top - tooltipEl.offsetHeight - 10 + "px";
      tooltipEl.style.left =
        rect.left + rect.width / 2 - tooltipEl.offsetWidth / 2 + "px";

      // Animation with GSAP if available
      if (typeof gsap !== "undefined") {
        gsap.from(tooltipEl, {
          y: 10,
          opacity: 0,
          duration: 0.3,
          ease: "power2.out",
        });
      } else {
        tooltipEl.style.opacity = 1;
      }

      this.addEventListener("mouseleave", function onMouseLeave() {
        if (typeof gsap !== "undefined") {
          gsap.to(tooltipEl, {
            opacity: 0,
            y: 10,
            duration: 0.2,
            ease: "power2.in",
            onComplete: () => {
              tooltipEl.remove();
            },
          });
        } else {
          tooltipEl.remove();
        }
        this.removeEventListener("mouseleave", onMouseLeave);
      });
    });
  });

  // Handle course status dropdown
  const statusDropdowns = document.querySelectorAll(".status-dropdown");
  statusDropdowns.forEach((dropdown) => {
    dropdown.addEventListener("change", function () {
      const courseId = this.getAttribute("data-course-id");
      const newStatus = this.value;

      // Change the status indicator color
      const statusCell = this.closest("tr").querySelector(".status-indicator");
      if (statusCell) {
        statusCell.className = "status-indicator";
        statusCell.classList.add(newStatus.toLowerCase());
      }

      // Here you would typically send an AJAX request to update the status
      updateCourseStatus(courseId, newStatus);
    });
  });

  // Initialize datepicker if available
  if (typeof flatpickr !== "undefined") {
    flatpickr(".datepicker", {
      dateFormat: "Y-m-d",
      disableMobile: true,
    });
  }

  // Handle notifications
  const notificationBell = document.querySelector(".notification-bell");
  const notificationDropdown = document.querySelector(".notification-dropdown");

  if (notificationBell && notificationDropdown) {
    notificationBell.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      notificationDropdown.classList.toggle("show");

      // Animate with GSAP if available
      if (
        typeof gsap !== "undefined" &&
        notificationDropdown.classList.contains("show")
      ) {
        gsap.from(notificationDropdown, {
          y: -10,
          opacity: 0,
          duration: 0.3,
          ease: "power2.out",
        });
      }
    });

    // Close dropdown when clicking outside
    document.addEventListener("click", function (e) {
      if (
        !notificationBell.contains(e.target) &&
        !notificationDropdown.contains(e.target)
      ) {
        notificationDropdown.classList.remove("show");
      }
    });
  }

  // Initialize custom file input
  const fileInputs = document.querySelectorAll(".custom-file-input");
  fileInputs.forEach((input) => {
    const fileLabel = input.nextElementSibling;

    input.addEventListener("change", function () {
      if (this.files.length > 0) {
        fileLabel.textContent = this.files[0].name;
      } else {
        fileLabel.textContent = "Chọn tập tin";
      }
    });
  });
});

// Function to handle course status updates
function updateCourseStatus(courseId, newStatus) {
  console.log(`Updating course ${courseId} status to ${newStatus}`);

  // Here you would typically send an AJAX request
  // Example:
  /*
    fetch('/api/courses/update-status', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            courseId: courseId,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Success:', data);
        // Show success notification
        showNotification('Trạng thái khóa học đã được cập nhật', 'success');
    })
    .catch((error) => {
        console.error('Error:', error);
        // Show error notification
        showNotification('Có lỗi xảy ra khi cập nhật trạng thái', 'error');
    });
    */

  // For demo purposes, just show a success notification
  showNotification("Trạng thái khóa học đã được cập nhật", "success");
}

// Function to handle assignment actions
function handleAssignmentAction(action, id, type) {
  console.log(`Handling ${action} for ${type} ID: ${id}`);

  switch (action) {
    case "edit":
      // Redirect to edit page
      // window.location.href = `/edit-${type}/${id}`;
      showNotification(`Đang mở trang chỉnh sửa ${type}`, "info");
      break;
    case "view":
      // Redirect to submissions page
      // window.location.href = `/${type}-submissions/${id}`;
      showNotification(`Đang xem bài nộp của ${type}`, "info");
      break;
    case "results":
      // Redirect to results page
      // window.location.href = `/${type}-results/${id}`;
      showNotification(`Đang xem kết quả của ${type}`, "info");
      break;
    default:
      console.log("Unknown action");
  }
}

// Setup event listeners for assignment actions
document.addEventListener("DOMContentLoaded", function () {
  // Assignment buttons
  document.querySelectorAll(".assignment-btn").forEach((btn) => {
    btn.addEventListener("click", function (e) {
      e.preventDefault();

      const action = this.querySelector("i").classList.contains("fa-edit")
        ? "edit"
        : this.querySelector("i").classList.contains("fa-eye")
        ? "view"
        : "results";

      const card = this.closest(".assignment-card");
      const tabContent = this.closest(".tab-content");

      let type = "assignment";
      if (tabContent.id === "quizzes-tab") type = "quiz";
      if (tabContent.id === "exams-tab") type = "exam";

      // Get ID from card (in a real app, you'd store this as a data attribute)
      const id = 1; // Placeholder

      handleAssignmentAction(action, id, type);
    });
  });
});

// Function to show notifications
function showNotification(message, type = "info") {
  const notification = document.createElement("div");
  notification.className = `notification ${type}`;
  notification.innerHTML = `
        <div class="notification-content">
            <div class="notification-message">${message}</div>
            <button class="notification-close">&times;</button>
        </div>
    `;

  document.body.appendChild(notification);

  // Animate with GSAP if available
  if (typeof gsap !== "undefined") {
    gsap.fromTo(
      notification,
      { x: 100, opacity: 0 },
      { x: 0, opacity: 1, duration: 0.5, ease: "power3.out" }
    );
  }

  // Handle close button
  const closeBtn = notification.querySelector(".notification-close");
  closeBtn.addEventListener("click", function () {
    dismissNotification(notification);
  });

  // Auto dismiss after 5 seconds
  setTimeout(() => {
    dismissNotification(notification);
  }, 5000);
}

// Function to dismiss notification
function dismissNotification(notification) {
  if (typeof gsap !== "undefined") {
    gsap.to(notification, {
      x: 100,
      opacity: 0,
      duration: 0.5,
      ease: "power3.in",
      onComplete: () => {
        notification.remove();
      },
    });
  } else {
    notification.remove();
  }
}

// Setup micro interactions
function setupMicroInteractions() {
  if (typeof gsap === "undefined") {
    console.warn("GSAP not loaded. Micro interactions will be limited.");
    return;
  }

  // Card hover effects
  document.querySelectorAll(".dashboard-card").forEach((card) => {
    card.addEventListener("mouseenter", () => {
      gsap.to(card, {
        y: -5,
        boxShadow: "0 12px 30px rgba(0,0,0,0.12)",
        duration: 0.3,
        ease: "power2.out",
      });
    });

    card.addEventListener("mouseleave", () => {
      gsap.to(card, {
        y: 0,
        boxShadow: "0 8px 24px rgba(0,0,0,0.08)",
        duration: 0.3,
        ease: "power2.out",
      });
    });
  });

  // Button hover effects
  document.querySelectorAll(".btn").forEach((btn) => {
    btn.addEventListener("mouseenter", () => {
      gsap.to(btn, { scale: 1.03, duration: 0.2, ease: "power1.out" });
    });

    btn.addEventListener("mouseleave", () => {
      gsap.to(btn, { scale: 1, duration: 0.2, ease: "power1.out" });
    });
  });

  // Sidebar item hover effects
  document.querySelectorAll(".sidebar-item").forEach((item) => {
    item.addEventListener("mouseenter", () => {
      const icon = item.querySelector("i");
      const text = item.querySelector("span");

      if (icon) gsap.to(icon, { x: 3, duration: 0.2, ease: "power1.out" });
      if (text) gsap.to(text, { x: 3, duration: 0.2, ease: "power1.out" });
    });

    item.addEventListener("mouseleave", () => {
      const icon = item.querySelector("i");
      const text = item.querySelector("span");

      if (icon) gsap.to(icon, { x: 0, duration: 0.2, ease: "power1.out" });
      if (text) gsap.to(text, { x: 0, duration: 0.2, ease: "power1.out" });
    });
  });

  // Table row hover effects
  document.querySelectorAll("table tbody tr").forEach((row) => {
    row.addEventListener("mouseenter", () => {
      gsap.to(row, {
        backgroundColor: "rgba(98, 74, 242, 0.05)",
        duration: 0.2,
        ease: "power1.out",
      });
    });

    row.addEventListener("mouseleave", () => {
      gsap.to(row, {
        backgroundColor: "transparent",
        duration: 0.2,
        ease: "power1.out",
      });
    });
  });
}
