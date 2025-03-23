// Lecture Management JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // File input handling
    const fileInput = document.getElementById('lesson_file');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const fileDisplay = document.getElementById('file-display');
            if (fileDisplay) {
                if (fileInput.files.length > 0) {
                    const fileName = fileInput.files[0].name;
                    fileDisplay.innerHTML = `
                        <span><i class="fas fa-file"></i>${fileName}</span>
                        <div class="file-actions">
                            <button type="button" class="delete-file" data-bs-toggle="tooltip" title="Remove File">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;
                    
                    // Add event listener to the delete button
                    const deleteButton = fileDisplay.querySelector('.delete-file');
                    if (deleteButton) {
                        deleteButton.addEventListener('click', function() {
                            fileInput.value = '';
                            fileDisplay.innerHTML = '';
                        });
                    }
                } else {
                    fileDisplay.innerHTML = '';
                }
            }
        });
    }

    // Handle more files click
    const moreFilesElement = document.querySelector('.more-files');
    if (moreFilesElement) {
        moreFilesElement.addEventListener('click', function() {
            const filesDetails = document.querySelector('.files-details');
            if (filesDetails) {
                filesDetails.style.display = filesDetails.style.display === 'none' ? 'block' : 'none';
            }
        });
    }

    // Close details
    const closeDetailsBtn = document.querySelector('.close-details');
    if (closeDetailsBtn) {
        closeDetailsBtn.addEventListener('click', function() {
            const filesDetails = document.querySelector('.files-details');
            if (filesDetails) {
                filesDetails.style.display = 'none';
            }
        });
    }
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Lesson reordering functionality
    let lessonOrder = {};
    const orderButtons = document.querySelectorAll('.order-btn');
    
    // Initialize lesson order
    document.querySelectorAll('.lesson-card').forEach(card => {
        const lessonId = card.dataset.lessonId;
        const orderIndex = parseInt(card.dataset.orderIndex);
        lessonOrder[lessonId] = orderIndex;
    });
    
    let originalOrder = {...lessonOrder};
    const saveButton = document.querySelector('.btn-order-save');
    
    // Handle order button clicks
    if (orderButtons.length > 0) {
        orderButtons.forEach(button => {
            button.addEventListener('click', function() {
                const lessonCard = this.closest('.lesson-card');
                const lessonId = lessonCard.dataset.lessonId;
                const direction = this.dataset.direction;
                const currentIndex = parseInt(lessonCard.dataset.orderIndex);
                
                let targetIndex;
                if (direction === 'up') {
                    targetIndex = currentIndex - 1;
                } else {
                    targetIndex = currentIndex + 1;
                }
                
                // Find the lesson card with the target index
                const targetCard = document.querySelector(`.lesson-card[data-order-index="${targetIndex}"]`);
                if (!targetCard) return;
                
                const targetId = targetCard.dataset.lessonId;
                
                // Swap order indices
                lessonCard.dataset.orderIndex = targetIndex;
                targetCard.dataset.orderIndex = currentIndex;
                
                // Update order numbers in the UI
                lessonCard.querySelector('.order-number').textContent = targetIndex;
                targetCard.querySelector('.order-number').textContent = currentIndex;
                
                // Update the order object
                lessonOrder[lessonId] = targetIndex;
                lessonOrder[targetId] = currentIndex;
                
                // Check if the order has changed from the original
                let hasOrderChanged = false;
                for (const id in lessonOrder) {
                    if (lessonOrder[id] !== originalOrder[id]) {
                        hasOrderChanged = true;
                        break;
                    }
                }
                
                // Highlight save button if order has changed
                if (hasOrderChanged) {
                    saveButton.classList.add('highlight');
                } else {
                    saveButton.classList.remove('highlight');
                }
                
                // Update disabled state of order buttons
                updateOrderButtonsState();
            });
        });
    }
    
    function updateOrderButtonsState() {
        const lessonCards = document.querySelectorAll('.lesson-card');
        const totalLessons = lessonCards.length;
        
        lessonCards.forEach(card => {
            const orderIndex = parseInt(card.dataset.orderIndex);
            const upButton = card.querySelector('.order-btn[data-direction="up"]');
            const downButton = card.querySelector('.order-btn[data-direction="down"]');
            
            if (upButton) {
                upButton.disabled = orderIndex === 1;
            }
            
            if (downButton) {
                downButton.disabled = orderIndex === totalLessons;
            }
        });
    }
    
    // Initialize order buttons state
    updateOrderButtonsState();
    
    // Add form submission for saving order
    if (saveButton) {
        saveButton.addEventListener('click', function() {
            // Create a form to submit the order data
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            // Add the lesson IDs and their order indices
            for (const lessonId in lessonOrder) {
                const lessonIdInput = document.createElement('input');
                lessonIdInput.type = 'hidden';
                lessonIdInput.name = 'lesson_ids[]';
                lessonIdInput.value = lessonId;
                form.appendChild(lessonIdInput);
                
                const orderIndexInput = document.createElement('input');
                orderIndexInput.type = 'hidden';
                orderIndexInput.name = 'order_indices[]';
                orderIndexInput.value = lessonOrder[lessonId];
                form.appendChild(orderIndexInput);
            }
            
            // Add a flag to indicate this is an order update
            const updateOrderInput = document.createElement('input');
            updateOrderInput.type = 'hidden';
            updateOrderInput.name = 'update_order';
            updateOrderInput.value = '1';
            form.appendChild(updateOrderInput);
            
            // Add the course ID
            const courseIdInput = document.getElementById('course_id');
            if (courseIdInput) {
                const courseIdFormInput = document.createElement('input');
                courseIdFormInput.type = 'hidden';
                courseIdFormInput.name = 'course_id';
                courseIdFormInput.value = courseIdInput.value;
                form.appendChild(courseIdFormInput);
            }
            
            // Submit the form
            document.body.appendChild(form);
            form.submit();
        });
    }
}); 