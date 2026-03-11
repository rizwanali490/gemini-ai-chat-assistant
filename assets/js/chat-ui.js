/**
 * Frontend Chat UI JavaScript for the Gemini AI Chat Assistant plugin.
 *
 * @package Gemini_AI_Chat_Assistant
 * @subpackage Gemini_AI_Chat_Assistant/assets/js
 * @author Rizwan ilyas <rizwan@rizwandevs.com>
 */

jQuery(document).ready(function($) {
    if (typeof gacaChat === 'undefined') {
        console.error('gacaChat object not found. Localization failed for frontend.');
        return;
    }

    const $chatContainer = $('#gaca-chat-container');
    const $chatMessages = $chatContainer.find('.gaca-chat-messages');
    const $chatLoader = $chatContainer.find('.gaca-chat-loader');
    const $chatInput = $chatContainer.find('.gaca-chat-input-area textarea');
    const $sendMessageButton = $chatContainer.find('.gaca-chat-input-area .send-message-button');
    const $topicButtonsContainer = $chatContainer.find('.gaca-topic-selection');
    const $fileUploadInput = $('#gaca-file-upload');
    const $fileUploadButton = $chatContainer.find('.gaca-upload-button');
    const $filePreviewContainer = $('#gaca-file-preview-container');

    let currentTopic = 'tax'; // Default topic
    let userHasInteracted = false; // Flag to check if user has sent a message

    // --- Helper Functions ---

    function scrollToBottom() {
        $chatMessages.scrollTop($chatMessages[0].scrollHeight);
    }

    function addMessage(sender, message, isHtml = false, fileUrl = null, fileType = null, fileName = '') {
        const messageClass = sender === 'user' ? 'user' : 'ai';
        const senderName = sender === 'user' ? 'You' : 'AI Assistant';
        const messageContent = isHtml ? message : $('<div/>').text(message).html();

        let fileAttachmentHtml = '';
        if (fileUrl && fileType) {
            if (fileType.startsWith('image/')) {
                fileAttachmentHtml = `<div class="gaca-message-attachment">
                    <img src="${fileUrl}" alt="Uploaded Image" style="max-width: 150px; height: auto;">
                    <span class="user-uploaded-image">${fileName}</span>
                </div>`;
            }
        }

        const $messageDiv = $(`
            <div class="gaca-message ${messageClass}">
                <strong>${senderName}:</strong>
                <p>${messageContent}</p>
                ${fileAttachmentHtml}
            </div>
        `);
        $chatMessages.append($messageDiv);
        scrollToBottom();
    }

    function showLoadingIndicator(show) {
        let $indicator = $chatContainer.find('.gaca-loading-indicator');
        if (show) {
            if ($indicator.length === 0) {
                $indicator = $('<div class="gaca-loading-indicator">' + gacaChat.messages.loading + '</div>');
                $chatLoader.append($indicator);
            }
            $indicator.show();
        } else {
            $indicator.hide();
        }
        scrollToBottom();
    }

    async function sendMessage() {
        const messageText = $chatInput.val().trim();
        const fileInput = $fileUploadInput[0];
        const hasFile = fileInput.files.length > 0;

        if (messageText === '' && !hasFile) {
            return;
        }

        // Add user message to chat immediately
        let uploadedFileData = null;
        if (hasFile) {
            const file = fileInput.files[0];
            // For display, we can use URL.createObjectURL
            uploadedFileData = {
                name: file.name,
                url: URL.createObjectURL(file),
                type: file.type
            };
            addMessage('user', messageText, false, uploadedFileData.url, uploadedFileData.type, uploadedFileData.name);
        } else {
            addMessage('user', messageText);
        }


        $chatInput.val('');
        showLoadingIndicator(true);
        $sendMessageButton.prop('disabled', true);

        // Reset file preview after sending
        $filePreviewContainer.empty();

        const formData = new FormData();
        formData.append('message', messageText);
        formData.append('topic', currentTopic);

        if (gacaChat.is_user_logged_in && hasFile) {
            formData.append('file', fileInput.files[0]);
            fileInput.value = '';
        }

        try {
            const headers = {
                'X-WP-Nonce': gacaChat.nonce
            };

            const response = await fetch(gacaChat.rest_url, {
                method: 'POST',
                headers: headers,
                body: formData,
            });

            const data = await response.json();

            if (data.success) {
                addMessage('ai', data.data.response, true);
            } else {
                addMessage('ai', gacaChat.messages.error);
            }
            userHasInteracted = true; // Mark that user has interacted
        } catch (error) {
            console.error('Fetch Error:', error);
            addMessage('ai', gacaChat.messages.error + ' ' + error.message);
        } finally {
            showLoadingIndicator(false);
            $sendMessageButton.prop('disabled', false);
            scrollToBottom();
        }
    }

    // NEW: Function to load conversation history for a given topic.
    async function loadHistory(topic) {
        $chatMessages.empty(); // Clear current messages.
        showLoadingIndicator(true);

        try {
            const headers = {
                'X-WP-Nonce': gacaChat.nonce
            };
            const response = await fetch(`${gacaChat.history_rest_url}?topic=${topic}`, {
                method: 'GET',
                headers: headers,
            });

            const data = await response.json();

            if (data.success && data.data.history && data.data.history.length > 0) {
                data.data.history.forEach(msg => {
                    addMessage(msg.message_type, msg.message_content, true, msg.file_url, msg.file_type, msg.file_name);
                });
                userHasInteracted = true; // History loaded means interaction occurred
            } else {
                // Display initial greeting if no history found for the topic.
                addMessage('ai', gacaChat.messages.initial_greeting);
                userHasInteracted = false; // Reset if no history means a fresh start
            }
        } catch (error) {
            console.error('Failed to load history:', error);
            addMessage('ai', gacaChat.messages.error + ' ' + error.message);
            userHasInteracted = false;
        } finally {
            showLoadingIndicator(false);
            scrollToBottom();
            $chatInput.focus();
        }
    }

    // NEW: Function to load user's existing topics.
    async function loadUserTopics() {
        if (!gacaChat.is_user_logged_in) {
            return; // Only load topics for logged-in users or if history is universally enabled.
        }

        try {
            const headers = {
                'X-WP-Nonce': gacaChat.nonce
            };
            const response = await fetch(`${gacaChat.history_rest_url}`, { // Fetch with no topic param to get topics list
                method: 'GET',
                headers: headers,
            });

            const data = await response.json();

            if (data.success && data.data.topics && data.data.topics.length > 0) {
                // Add any topics from history that are not in the predefined list
                data.data.topics.forEach(historyTopic => {
                    if (!gacaChat.topics[historyTopic]) {
                        gacaChat.topics[historyTopic] = historyTopic.charAt(0).toUpperCase() + historyTopic.slice(1); // Simple capitalization
                    }
                });

                // Re-render topic buttons to include new ones
                $topicButtonsContainer.empty(); // Clear existing buttons
                Object.keys(gacaChat.topics).forEach(function(key) {
                    const $button = $(`<button type="button" data-topic="${key}">${gacaChat.topics[key]}</button>`);
                    $topicButtonsContainer.append($button);
                    if (key === currentTopic) {
                        $button.addClass('active');
                    }
                });
            }
        } catch (error) {
            console.error('Failed to load user topics:', error);
        }
    }

    // --- Event Handlers ---

    // Handle file input change to show a preview.
    $fileUploadInput.on('change', function(e) {
        $filePreviewContainer.empty(); // Clear any existing previews.
        const file = e.target.files[0];
        if (file) {
            // Validate file type
            const allowedTypes = ['image/jpg', 'image/jpeg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Only JPG JPEG and PNG images are allowed.');
                $fileUploadInput.val('');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(event) {
                const previewHtml = `
                    <div class="gaca-file-preview-wrapper">
                        <div class="gaca-file-preview">
                            <img src="${event.target.result}" alt="File preview" class="gaca-file-preview-image" />
                            <span class="gaca-file-remove-button" title="Remove file">&times;</span>
                        </div>
                    </div>
                `;
                $filePreviewContainer.append(previewHtml);
            };
            reader.readAsDataURL(file);
        }
    });

    // NEW: Handle removing the file preview.
    $chatContainer.on('click', '.gaca-file-remove-button', function() {
        $fileUploadInput.val(''); // Clear the file input.
        $filePreviewContainer.empty(); // Remove the preview.
    });

    $sendMessageButton.on('click', sendMessage);

    $chatInput.on('keypress', function(e) {
        if (e.which === 13 && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Handle topic button clicks.
    $topicButtonsContainer.on('click', 'button', function() {
        $topicButtonsContainer.find('button').removeClass('active');
        $(this).addClass('active');
        currentTopic = $(this).data('topic');
        addMessage('system', `${gacaChat.messages.switched_topic} ${gacaChat.topics[currentTopic]}`);
        loadHistory(currentTopic); // NEW: Load history for the selected topic.
    });

    // Initialize topic buttons and select default.
    Object.keys(gacaChat.topics).forEach(function(key) {
        const $button = $(`<button class="" type="button" data-topic="${key}">${gacaChat.topics[key]}</button>`);
        $topicButtonsContainer.append($button);
        if (key === currentTopic) {
            $button.addClass('active');
        }
    });

    $fileUploadButton.on('click', function() {
        if (!gacaChat.is_user_logged_in) {
            alert(gacaChat.messages.upload_not_allowed);
            return;
        }
        $fileUploadInput.trigger('click');
    });

    if (!gacaChat.is_user_logged_in) {
        $fileUploadButton.prop('disabled', true).attr('title', gacaChat.messages.upload_not_allowed).hide();
    }

    // Initial load:
    // First, try to load any existing topics (for logged-in users).
    // Then, load history for the default topic.
    // Ensure the chat container is initially visible as it's no longer toggled.
    $chatContainer.show(); // Ensure the container is visible.

    // Load initial history based on the default topic.
    loadHistory(currentTopic); // Load history immediately.

    // Load user-specific topics if logged in (this will re-render topic buttons).
    if (gacaChat.is_user_logged_in) {
        loadUserTopics();
    }

    // Initial focus.
    $chatInput.focus();
});