document.addEventListener('DOMContentLoaded', () => {
    // Get references to the elements
    const level1 = document.getElementById('level-1');
    const level2 = document.getElementById('level-2');
    const mainButton = document.getElementById('main-button');
    const clickCounterDisplay = document.getElementById('click-counter');

    // Define the goal
    const clicksToAdvance = 17;
    let currentClicks = 0;

    // Add an event listener to the main button
    mainButton.addEventListener('click', () => {
        // Increment the click count
        currentClicks++;

        // Update the counter display
        clickCounterDisplay.textContent = `Clicks: ${currentClicks}`;

        // Check if the user has reached the goal
        if (currentClicks >= clicksToAdvance) {
            console.log('Advancing to the next level!');

            // Hide level 1
            level1.style.display = 'none';

            // Show level 2
            level2.style.display = 'block';
        }
    });
});






document.addEventListener('DOMContentLoaded', () => {
    const commentForm = document.getElementById('comment-form');
    const commentInput = document.getElementById('comment-input');
    const commentsList = document.getElementById('comments-list');

    // Fetch and display existing comments from the PHP backend
    const loadComments = async () => {
        try {
            // Updated to call the PHP script for getting comments
            const response = await fetch('api.php?action=get_comments');
            const comments = await response.json();
            commentsList.innerHTML = '';
            comments.forEach(comment => {
                const commentElement = document.createElement('div');
                commentElement.classList.add('comment');
                // Use textContent to prevent XSS
                commentElement.textContent = comment.text;
                commentsList.appendChild(commentElement);
            });
        } catch (error) {
            console.error('Error loading comments:', error);
        }
    };

    // Handle form submission
    commentForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const newCommentText = commentInput.value;

        if (newCommentText.trim() === '') return;

        try {
            // Updated to call the PHP script for adding a comment
            const response = await fetch('api.php?action=add_comment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ text: newCommentText }),
            });

            if (response.ok) {
                commentInput.value = ''; // Clear input field
                loadComments(); // Reload comments to show the new one
            } else {
                console.error('Failed to submit comment.');
            }
        } catch (error) {
            console.error('Error submitting comment:', error);
        }
    });

    // Initial load of comments
    loadComments();
});