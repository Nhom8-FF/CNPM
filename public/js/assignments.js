/**
 * Assignments & Assessments JS
 */

document.addEventListener("DOMContentLoaded", function () {
  console.log("Assignments JS loaded");

  // Tab Navigation
  const tabButtons = document.querySelectorAll(".tab-btn");
  const tabContents = document.querySelectorAll(".tab-content");

  if (tabButtons.length && tabContents.length) {
    console.log(
      `Found ${tabButtons.length} tab buttons and ${tabContents.length} tab contents`
    );

    tabButtons.forEach((button) => {
      button.addEventListener("click", function (e) {
        e.preventDefault();
        const targetId = this.getAttribute("data-tab");
        console.log(`Tab clicked: ${targetId}`);

        // Remove active class from all buttons and content
        tabButtons.forEach((btn) => btn.classList.remove("active"));
        tabContents.forEach((content) => content.classList.remove("active"));

        // Add active class to current button and content
        this.classList.add("active");
        const targetContent = document.getElementById(targetId);
        if (targetContent) {
          targetContent.classList.add("active");
        } else {
          console.error(`Target content with ID ${targetId} not found`);
        }

        // Update URL hash
        window.location.hash = targetId;
      });
    });

    // Check for hash in URL on page load
    if (window.location.hash) {
      const hash = window.location.hash.substring(1);
      const targetButton = document.querySelector(
        `.tab-btn[data-tab="${hash}"]`
      );
      if (targetButton) {
        targetButton.click();
      }
    }
  } else {
    console.warn("Tab navigation elements not found");
  }

  // Course and Status Filters
  const courseFilter = document.getElementById("course-filter");
  const statusFilter = document.getElementById("status-filter");

  if (courseFilter && statusFilter) {
    console.log("Filters found, initializing filter functionality");

    courseFilter.addEventListener("change", applyFilters);
    statusFilter.addEventListener("change", applyFilters);
  } else {
    console.warn("Filter elements not found");
  }

  function applyFilters() {
    const selectedCourse = courseFilter ? courseFilter.value : "all";
    const selectedStatus = statusFilter ? statusFilter.value : "all";

    console.log(
      `Applying filters: course=${selectedCourse}, status=${selectedStatus}`
    );

    // Get the active tab
    const activeTab = document.querySelector(".tab-content.active");
    if (!activeTab) {
      console.warn("No active tab found");
      return;
    }

    // Get all cards in the active tab
    const cards = activeTab.querySelectorAll(".assignment-card");
    if (cards.length === 0) {
      console.log("No assignment cards found in active tab");
      return;
    }

    console.log(`Found ${cards.length} cards in active tab`);

    let visibleCount = 0;
    cards.forEach((card) => {
      const courseId = card.getAttribute("data-course");
      const status = card.getAttribute("data-status");

      const courseMatch =
        selectedCourse === "all" || courseId === selectedCourse;
      const statusMatch = selectedStatus === "all" || status === selectedStatus;

      if (courseMatch && statusMatch) {
        card.style.display = "";
        visibleCount++;
      } else {
        card.style.display = "none";
      }
    });

    console.log(`${visibleCount} cards visible after filtering`);

    // Show empty state if no cards are visible
    const emptyState = activeTab.querySelector(".empty-state");
    if (emptyState) {
      if (visibleCount === 0 && cards.length > 0) {
        // Create and show "no results" message
        let noResults = activeTab.querySelector(".no-results-message");
        if (!noResults) {
          console.log("Creating no results message");
          noResults = document.createElement("div");
          noResults.className = "empty-state no-results-message";
          noResults.innerHTML = `
            <img src="${
              typeof BASE_URL !== "undefined" ? BASE_URL : ""
            }public/images/no-results.svg" alt="Không có kết quả">
            <h3>Không tìm thấy kết quả</h3>
            <p>Không có bài tập nào phù hợp với bộ lọc được chọn</p>
          `;
          activeTab.appendChild(noResults);
        }
        noResults.style.display = "";

        // Hide the original empty state if it exists
        if (cards.length > 0) {
          emptyState.style.display = "none";
        }
      } else {
        // Hide "no results" message if it exists
        const noResults = activeTab.querySelector(".no-results-message");
        if (noResults) {
          noResults.style.display = "none";
        }

        // Show empty state only if there are no cards at all
        emptyState.style.display = cards.length === 0 ? "" : "none";
      }
    }
  }

  // Initialize dropdown menu
  const dropdowns = document.querySelectorAll(".dropdown");
  if (dropdowns.length) {
    dropdowns.forEach((dropdown) => {
      const button = dropdown.querySelector("button");
      const content = dropdown.querySelector(".dropdown-content");

      if (button && content) {
        button.addEventListener("click", function (e) {
          e.stopPropagation();
          content.classList.toggle("show");
        });

        // Close dropdown when clicking outside
        document.addEventListener("click", function () {
          content.classList.remove("show");
        });
      }
    });
  }

  // Tab switching functionality
  const tabButtons = document.querySelectorAll(".tab-btn");
  const tabContents = document.querySelectorAll(".tab-content");

  tabButtons.forEach((button) => {
    button.addEventListener("click", () => {
      // Remove active class from all buttons and contents
      tabButtons.forEach((btn) => btn.classList.remove("active"));
      tabContents.forEach((content) => content.classList.remove("active"));

      // Add active class to clicked button and corresponding content
      button.classList.add("active");
      const tabId = `${button.dataset.tab}-tab`;
      document.getElementById(tabId).classList.add("active");
    });
  });

  // Course selection for lesson dropdown on create/edit assignment page
  const courseSelect = document.getElementById("course");
  const lessonSelect = document.getElementById("lesson");

  if (courseSelect && lessonSelect) {
    courseSelect.addEventListener("change", function () {
      const courseId = this.value;

      // Reset the lesson dropdown
      lessonSelect.innerHTML = '<option value="">-- Chọn bài học --</option>';
      lessonSelect.disabled = true;

      if (courseId) {
        // If we have lessons for this course
        if (
          courseLessons &&
          courseLessons[courseId] &&
          courseLessons[courseId].length > 0
        ) {
          // Enable the lesson dropdown
          lessonSelect.disabled = false;

          // Add lesson options
          courseLessons[courseId].forEach((lesson) => {
            const option = document.createElement("option");
            option.value = lesson.lesson_id;
            option.textContent = lesson.title;
            lessonSelect.appendChild(option);
          });
        }
      }
    });
  }

  // Rich text editor for assignment instructions
  const richEditor = document.getElementById("rich-editor");
  const instructionsInput = document.getElementById("instructions");

  if (richEditor && instructionsInput) {
    // Update hidden input before form submission
    document.querySelector("form").addEventListener("submit", function () {
      instructionsInput.value = richEditor.innerHTML;
    });

    // Setup toolbar buttons
    const toolbarButtons = document.querySelectorAll(".toolbar-btn");

    toolbarButtons.forEach((button) => {
      button.addEventListener("click", function () {
        const command = this.dataset.command;

        if (command === "createlink") {
          const url = prompt("Nhập đường dẫn:");
          if (url) {
            document.execCommand(command, false, url);
          }
        } else if (command === "h1" || command === "h2" || command === "h3") {
          document.execCommand("formatBlock", false, command.toUpperCase());
        } else {
          document.execCommand(command, false, null);
        }

        richEditor.focus();
      });
    });
  }

  // File upload preview
  const fileInput = document.getElementById("file-input");
  const fileList = document.getElementById("file-list");

  if (fileInput && fileList) {
    fileInput.addEventListener("change", function () {
      fileList.innerHTML = "";

      Array.from(this.files).forEach((file) => {
        const fileItem = document.createElement("div");
        fileItem.className = "file-item";

        // Get appropriate icon
        let iconClass = "fa-file";
        if (file.type.startsWith("image/")) {
          iconClass = "fa-file-image";
        } else if (file.type.startsWith("video/")) {
          iconClass = "fa-file-video";
        } else if (file.type.startsWith("audio/")) {
          iconClass = "fa-file-audio";
        } else if (file.type.includes("pdf")) {
          iconClass = "fa-file-pdf";
        } else if (file.type.includes("word")) {
          iconClass = "fa-file-word";
        } else if (
          file.type.includes("excel") ||
          file.type.includes("spreadsheet")
        ) {
          iconClass = "fa-file-excel";
        } else if (
          file.type.includes("zip") ||
          file.type.includes("compressed")
        ) {
          iconClass = "fa-file-archive";
        }

        fileItem.innerHTML = `
          <div class="file-item-name">
            <i class="fas ${iconClass}"></i>
            ${file.name}
          </div>
          <span class="file-item-size">${formatFileSize(file.size)}</span>
        `;

        fileList.appendChild(fileItem);
      });
    });

    // Drag and drop support
    const fileUpload = document.querySelector(".file-upload");

    ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
      fileUpload.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
      e.preventDefault();
      e.stopPropagation();
    }

    ["dragenter", "dragover"].forEach((eventName) => {
      fileUpload.addEventListener(eventName, highlight, false);
    });

    ["dragleave", "drop"].forEach((eventName) => {
      fileUpload.addEventListener(eventName, unhighlight, false);
    });

    function highlight() {
      fileUpload.classList.add("highlight");
    }

    function unhighlight() {
      fileUpload.classList.remove("highlight");
    }

    fileUpload.addEventListener("drop", handleDrop, false);

    function handleDrop(e) {
      const dt = e.dataTransfer;
      const files = dt.files;
      fileInput.files = files;

      // Trigger change event
      const event = new Event("change");
      fileInput.dispatchEvent(event);
    }
  }

  // Helper function to format file size
  function formatFileSize(bytes) {
    if (bytes === 0) return "0 Bytes";

    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
  }
});
