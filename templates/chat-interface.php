<?php
/**
 * Template for the main chat interface container.
 *
 * This file contains the HTML structure for the frontend chat assistant.
 * JavaScript will populate and manage the dynamic content within this structure.
 *
 * @package Gemini_AI_Chat_Assistant
 * @subpackage Gemini_AI_Chat_Assistant/templates
 * @author Rizwan ilyas <rizwan@rizwandevs.com>
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>


<!-- Main Chat Container -->
 <div class="ai-assistant-chat-wrapper">
     <div id="gaca-chat-container">
         <div class="gaca-chat-header">
             <span><?php esc_html_e( 'AI Assistant', 'gemini-ai-chat-assistant' ); ?></span>
         </div>
     
         <div class="gaca-topics">
            <p class="gaca-topic-selection-heading">Select topic and ask anything to our AI assistant</p>
             <div class="gaca-topic-selection">
                 <!-- Topic buttons will be dynamically inserted here by JavaScript -->
             </div>
         </div>
     
         <div class="gaca-chat-messages">
             <!-- Chat messages will be appended here by JavaScript -->
             <div class="gaca-message ai">
                 <strong><?php esc_html_e( 'AI Assistant:', 'gemini-ai-chat-assistant' ); ?></strong>
                 <p><?php esc_html_e( 'Hello! How can I assist you today?', 'gemini-ai-chat-assistant' ); ?></p>
             </div>
         </div>
     
         <!-- Chat loading indicator -->
         <div class="gaca-chat-loader"></div>
         
         <!-- File previwer -->
         <div class="gaca-file-preview-container" id="gaca-file-preview-container"></div>
         
         <!-- Input area -->
         <div class="gaca-chat-input-area">
             <input type="file" id="gaca-file-upload" style="display: none;" accept="image/*">
             <button type="button" class="gaca-upload-button" title="<?php esc_attr_e( 'Upload File (Members Only)', 'gemini-ai-chat-assistant' ); ?>"><?php esc_html_e('Upload File', 'gemini-ai-chat-assistant'); ?></button>
             <textarea id="gaca-chat-input" placeholder="<?php esc_attr_e( 'Type your message...', 'gemini-ai-chat-assistant' ); ?>" rows="1"></textarea>
             <button type="button" class="send-message-button"><?php esc_html_e( 'Send', 'gemini-ai-chat-assistant' ); ?></button>
         </div>
     </div>
 </div>