<?php
/**
 * AI Webpage Chat Widget - Frontend Loader code snippet
 * v2.8-Lite - Enhanced Security: Added Bot Trap
 * 
 * by Igor Lipkanou (igor@magicquickwins.com) and Claude Sonet 4.5
 * 
 * Displays the chat widget on specific WordPress pages/posts you choose.
 * Features:
 * - Inline styles (no external dependencies needed)
 * - Content filtering (removes personal info from page context)
 * - Improved name recognition
 * - Transcript reminder
 * - Friendly error messages
 * - Bot trap field for automated bot protection (NEW in v2.8)
 */

add_action('wp_footer', function() {
    
    // =================================================================
    // CONFIGURATION - Edit page IDs where widget should appear
    // =================================================================
    
    $display_on_pages = [
        123,  // Example: homepage
        456,  // Example: checkout page
        // Add more page IDs as needed
    ];
    
    // =================================================================
    // PAGE DETECTION
    // =================================================================
    
    // Only load on specified pages
    if ( is_singular() && in_array(get_the_ID(), $display_on_pages) ) {
        
        // =================================================================
        // BACKEND CONNECTION - Prepare configuration values
        // =================================================================
        
        $backend_url = rtrim(get_rest_url(null, 'my-chat-assistant/v1'), '/');
        $nonce = wp_create_nonce('wp_rest');
        
        // =================================================================
        // OUTPUT: Inject configuration first, then the widget
        // =================================================================
        
        // Step 1: Output container
        echo '<div id="ai-chat-widget-root"></div>';
        
        // Step 2: Output import map for React
        echo '<script type="importmap">
{
  "imports": {
    "react": "https://esm.sh/react@^19.2.0",
    "react-dom/client": "https://esm.sh/react-dom@^19.2.0/client"
  }
}
</script>';
        
        // Step 3: Inject configuration as JavaScript variable
        echo '<script type="text/javascript">';
        echo 'window.AI_CHAT_CONFIG = {';
        echo '  backendUrl: ' . json_encode($backend_url) . ',';
        echo '  nonce: ' . json_encode($nonce) . ',';
        echo '  model: "gemini-2.5-flash-lite"'; // You can change the model here
        echo '};';
        echo '</script>';
        
        // Step 4: Output the main widget script
        echo <<<'HTML'
<script type="module">
import React from 'react';
import ReactDOM from 'react-dom/client';

(function() {
    const e = React.createElement;

    // =================================================================
    // INLINE STYLES - All styling included here
    // =================================================================
    const injectStyles = () => {
        const style = document.createElement('style');
        style.textContent = `
            /* Chat Widget Styles */
            .chat-widget-container { position: fixed; bottom: 12px; right: 12px; width: calc(100vw - 24px); max-width: 24rem; height: 60vh; max-height: 500px; background-color: #E5DDD5; background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; z-index: 9999; }
            @media (min-width: 640px) { .chat-widget-container { bottom: 20px; right: 20px; width: 100%; height: 70vh; max-height: 600px; } }
            .chat-header { background-color: #005E54; color: white; padding: 12px; display: flex; align-items: center; border-radius: 8px 8px 0 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .chat-avatar { width: 40px; height: 40px; margin-right: 12px; }
            .chat-header-info { flex: 1; }
            .chat-header-title { font-size: 18px; font-weight: 600; line-height: 1.2; }
            .chat-header-subtitle { font-size: 12px; color: #D1D5DB; line-height: 1.2; }
            .chat-close-btn { color: white; background: none; border: none; cursor: pointer; padding: 4px; }
            .chat-close-btn:hover { color: #D1D5DB; }
            .chat-messages { flex: 1; padding: 16px; overflow-y: auto; }
            .chat-message-wrapper { display: flex; margin-bottom: 12px; }
            .chat-message-wrapper.user { justify-content: flex-end; }
            .chat-message-wrapper.model { justify-content: flex-start; }
            .chat-message { padding: 8px 12px; border-radius: 12px; max-width: 85%; word-wrap: break-word; box-shadow: 0 1px 2px rgba(0,0,0,0.1); font-size: 14px; }
            .chat-message.user { background-color: #DCF8C6; color: #1F2937; border-radius: 12px 12px 0 12px; }
            .chat-message.model { background-color: white; color: #1F2937; border-radius: 12px 12px 12px 0; }
            .chat-message strong { font-weight: 600; }
            .chat-message ul { list-style-type: disc; margin-left: 20px; padding-left: 8px; margin-top: 4px; margin-bottom: 4px; }
            .chat-message li { margin-bottom: 4px; }
            .chat-message p { margin-bottom: 8px; }
            .chat-message p:last-child { margin-bottom: 0; }
            .chat-loading { display: flex; justify-content: flex-start; }
            .chat-loading-bubble { padding: 8px 12px; border-radius: 12px; background-color: white; color: #1F2937; box-shadow: 0 1px 2px rgba(0,0,0,0.1); border-radius: 12px 12px 12px 0; }
            .chat-loading-dots { display: flex; align-items: center; gap: 4px; }
            .chat-loading-dot { height: 8px; width: 8px; background-color: #6B7280; border-radius: 50%; animation: bounce 1.4s infinite ease-in-out; }
            .chat-loading-dot:nth-child(1) { animation-delay: -0.32s; }
            .chat-loading-dot:nth-child(2) { animation-delay: -0.16s; }
            @keyframes bounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }
            .chat-footer { padding: 8px; border-top: 1px solid #E5E7EB; background-color: transparent; }
            .chat-form { display: flex; align-items: center; gap: 8px; }
            .chat-input { flex: 1; padding: 8px 16px; background-color: #F3F4F6; border-radius: 20px; border: none; outline: none; font-size: 14px; transition: background-color 0.2s, box-shadow 0.2s; }
            .chat-input:focus { background-color: white; box-shadow: 0 0 0 2px #005E54; }
            .chat-input:disabled { background-color: #E5E7EB; cursor: not-allowed; }
            .chat-send-btn { padding: 12px; background-color: #008069; color: white; border-radius: 50%; border: none; cursor: pointer; transition: background-color 0.2s, box-shadow 0.2s; display: flex; align-items: center; justify-content: center; }
            .chat-send-btn:hover:not(:disabled) { background-color: #005E54; }
            .chat-send-btn:focus { outline: none; box-shadow: 0 0 0 2px #005E54; }
            .chat-send-btn:disabled { background-color: #9CA3AF; cursor: not-allowed; }
            .chat-float-btn { position: fixed; bottom: 20px; right: 20px; background-color: #008069; color: white; padding: 12px; border-radius: 50%; box-shadow: 0 4px 12px rgba(0,0,0,0.3); border: none; cursor: pointer; transition: transform 0.2s, background-color 0.2s, box-shadow 0.2s; z-index: 9999; }
            .chat-float-btn:hover { background-color: #005E54; transform: scale(1.1); }
            .chat-float-btn:focus { outline: none; box-shadow: 0 0 0 2px #005E54, 0 4px 12px rgba(0,0,0,0.3); }
        `;
        document.head.appendChild(style);
    };
    
    // Inject styles immediately
    injectStyles();

    const App = () => {
        
        // =================================================================
        // ICON COMPONENTS - SVG icons for UI
        // =================================================================
        
        const BotIcon = (props) => e("svg", { ...props, xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", fill: "none", stroke: "currentColor", strokeWidth: "2", strokeLinecap: "round", strokeLinejoin: "round" }, e("path", { d: "M12 8V4H8" }), e("rect", { width: "16", height: "12", x: "4", y: "8", rx: "2" }), e("path", { d: "M2 14h2" }), e("path", { d: "M20 14h2" }), e("path", { d: "M15 13v2" }), e("path", { d: "M9 13v2" }));
        
        const CloseIcon = (props) => e("svg", { ...props, xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", fill: "none", stroke: "currentColor", strokeWidth: "2", strokeLinecap: "round", strokeLinejoin: "round", width: "24", height: "24" }, e("line", { x1: "18", y1: "6", x2: "6", y2: "18" }), e("line", { x1: "6", y1: "6", x2: "18", y2: "18" }));
        
        const SendIcon = (props) => e("svg", { ...props, xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", fill: "none", stroke: "currentColor", strokeWidth: "2", strokeLinecap: "round", strokeLinejoin: "round", width: "24", height: "24" }, e("line", { x1: "22", y1: "2", x2: "11", y2: "13" }), e("polygon", { points: "22 2 15 22 11 13 2 9 22 2" }));
        
        const MaggieAvatar = (props) => e("svg", { ...props, viewBox: "0 0 40 40", fill: "none", xmlns: "http://www.w3.org/2000/svg", "aria-hidden": "true" }, e("circle", { cx: "20", cy: "20", r: "20", fill: "#E0E0E0" }), e("path", { d: "M10 28C10 24.6667 13.3333 22 20 22C26.6667 22 30 24.6667 30 28", stroke: "#757575", strokeWidth: "2", strokeLinecap: "round" }), e("circle", { cx: "20", cy: "16", r: "4", fill: "#757575" }));
        
        // =================================================================
        // MARKDOWN RENDERER - Converts simple markdown to HTML
        // =================================================================
        
        const MarkdownRenderer = ({ content }) => {
            // Process **bold** syntax
            const processedContent = content.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            const lines = processedContent.split('\n');
            const renderedElements = [];
            let listItems = [];
            
            // Helper to flush accumulated list items into a <ul>
            const flushList = () => {
                if (listItems.length > 0) {
                    renderedElements.push(
                        e('ul', { key: `ul-${renderedElements.length}` }, 
                            listItems.map((item, idx) => 
                                e('li', { key: idx, dangerouslySetInnerHTML: { __html: item } })
                            )
                        )
                    );
                    listItems = [];
                }
            };
            
            // Process each line
            lines.forEach((line, index) => {
                const trimmedLine = line.trim();
                if (trimmedLine.startsWith('* ')) {
                    // This is a list item
                    listItems.push(trimmedLine.substring(2).trim());
                } else {
                    // Not a list - flush any pending list, then add paragraph
                    flushList();
                    if (trimmedLine) {
                        renderedElements.push(
                            e('p', { key: `p-${index}`, dangerouslySetInnerHTML: { __html: trimmedLine } })
                        );
                    }
                }
            });
            
            // Flush any remaining list items
            flushList();
            return e(React.Fragment, null, ...renderedElements);
        };

        // =================================================================
        // MAIN CHAT WIDGET COMPONENT
        // =================================================================
        
        const ChatWidget = ({ config }) => {
            
            // --- STATE MANAGEMENT ---
            const [isOpen, setIsOpen] = React.useState(false);
            const [isLoading, setIsLoading] = React.useState(false);
            const [messages, setMessages] = React.useState([]);
            const [userInput, setUserInput] = React.useState('');
            const [pageContext, setPageContext] = React.useState('');
            const [userName, setUserName] = React.useState('');
            const [userEmail, setUserEmail] = React.useState('');
            const [conversationState, setConversationState] = React.useState('ASKING_NAME');
            const [isBackendConfigured, setIsBackendConfigured] = React.useState(false);
            const messagesEndRef = React.useRef(null);
            const inputRef = React.useRef(null);

            // --- EFFECTS ---
            
            // Check backend configuration
            React.useEffect(() => { 
                setIsBackendConfigured(!!config.backendUrl && !!config.nonce); 
            }, [config.backendUrl, config.nonce]);
            
            // Extract page content for AI context (with filtering)
            React.useEffect(() => {
                const selectors = [
                    'main',
                    '.op-page-content',
                    '.opb-section-content',
                    '.op3-element[data-op3-element-type="column"]',
                    'article',
                    '.entry-content',
                    '.page-content',
                    '#content',
                    'body'
                ];
                
                let contentFound = false;
                
                for (const selector of selectors) {
                    const element = document.querySelector(selector);
                    if (element) {
                        let contentText = element.innerText.trim();
                        
                        // Must have substantial content
                        if (contentText.length > 100) {
                            // Filter: Remove emails and phone numbers for privacy
                            contentText = contentText
                                .replace(/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/g, '[EMAIL]')
                                .replace(/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/g, '[PHONE]')
                                .replace(/\b\+?\d{1,3}[-.\s]?\(?\d{1,4}\)?[-.\s]?\d{1,4}[-.\s]?\d{1,9}\b/g, '[PHONE]');
                            
                            setPageContext(contentText);
                            console.log(`AI Chat Widget: Found content using selector: ${selector} (${contentText.length} characters, filtered)`);
                            contentFound = true;
                            break;
                        }
                    }
                }
                
                if (!contentFound) {
                    console.warn("AI Chat Widget: Could not find page content. Widget will work, but without page-specific context.");
                    setPageContext('');
                }
            }, []);
            
            // Show initial greeting when chat opens
            React.useEffect(() => {
                if (isOpen && messages.length === 0) {
                    if (isBackendConfigured) {
                        setMessages([{ 
                            role: 'model', 
                            content: "Hi! I'm Maggie 😊\nI'm your personal guide to this page. I can quickly answer any questions so you can make the best decision.\nWhat's your name?" 
                        }]);
                    } else {
                        setMessages([{ 
                            role: 'model', 
                            content: "I'm sorry, my connection to the website is not configured correctly. Please let the site administrator know about this issue." 
                        }]);
                        console.error("AI Chat Widget: Backend URL or nonce is missing.");
                    }
                }
            }, [isOpen, isBackendConfigured, messages.length]);

            // Auto-scroll to bottom when messages change
            React.useEffect(() => {
                messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
                if (!isLoading) inputRef.current?.focus();
            }, [messages, isLoading]);

            // --- EVENT HANDLERS ---
            
            // Handle input changes (switches between text/email based on state)
            const handleUserInput = (ev) => {
                if (conversationState === 'ASKING_EMAIL') {
                    setUserEmail(ev.target.value);
                } else {
                    setUserInput(ev.target.value);
                }
            };
            
            // Generic backend API call function
            const callBackend = async (action, payload) => {
                try {
                    const response = await fetch(config.backendUrl + '/chat', {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json', 
                            'X-WP-Nonce': config.nonce 
                        },
                        body: JSON.stringify({ 
                            action, 
                            payload: { ...payload, model: config.model } 
                        })
                    });
                    
                    if (!response.ok) {
                        const errorData = await response.json().catch(() => ({ message: "Unknown error" }));
                        throw new Error(errorData.message || `HTTP ${response.status}`);
                    }
                    
                    return (await response.json()).text;
                } catch (error) {
                    console.error('Backend API call failed:', error);
                    throw error;
                }
            };

            // Send transcript to backend (email delivery)
            // NEW in v2.8: Added bot trap field
            const sendDataToBackend = async () => {
                setIsLoading(true);
                setConversationState('SENDING_TRANSCRIPT');
                setMessages(prev => [...prev, { 
                    role: 'model', 
                    content: "Got it. I'm preparing a summary..." 
                }]);
                
                try {
                    // Generate summary
                    const summary = await callBackend('summarizeConversation', { history: messages });
                    setMessages(prev => [...prev, { 
                        role: 'model', 
                        content: "Okay, sending the transcript..." 
                    }]);
                    
                    // Submit to /lead endpoint
                    // NEW in v2.8: Added 'website' bot trap field
                    // This field is NEVER filled by real visitors (it's not visible)
                    // Automated bots that fill all forms will fill this field, and the backend will reject them
                    const response = await fetch(config.backendUrl + '/lead', {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json', 
                            'X-WP-Nonce': config.nonce 
                        },
                        body: JSON.stringify({ 
                            name: userName, 
                            email: userEmail, 
                            transcript: messages, 
                            summary: summary,
                            website: ''  // Bot trap field - should always be empty for real visitors
                        })
                    });
                    
                    if (!response.ok) throw new Error('Failed to send transcript');
                    await response.json();
                    
                    setMessages(prev => [...prev, { 
                        role: 'model', 
                        content: "Done! The transcript has been sent. Feel free to start a new chat." 
                    }]);
                    setConversationState('TRANSCRIPT_SENT');
                } catch (error) {
                    console.error('Failed to send transcript:', error);
                    setMessages(prev => [...prev, { 
                        role: 'model', 
                        content: "I'm having trouble sending the transcript right now. Please try again in a moment." 
                    }]);
                    setConversationState('ASKING_EMAIL');
                } finally { 
                    setIsLoading(false); 
                }
            };
            
            // Handle form submission - behavior changes based on conversation state
            const handleSubmit = async (ev) => {
                ev.preventDefault();
                if (!isBackendConfigured) return;

                // STATE 1: Asking for user's name
                if (conversationState === 'ASKING_NAME') {
                    if (!userInput.trim()) return;
                    
                    const nameInput = userInput.trim();
                    setMessages(prev => [...prev, { role: 'user', content: nameInput }]);
                    setUserInput(''); 
                    setIsLoading(true);
                    
                    try {
                        const responseText = await callBackend('extractNameAndGreet', { userInput: nameInput });
                        const { name, greeting } = JSON.parse(responseText);
                        setUserName(name);
                        
                        // Add greeting + transcript reminder
                        setMessages(prev => [...prev, 
                            { role: 'model', content: greeting },
                            { role: 'model', content: "By the way, when we're finished chatting, I can send you a transcript of our conversation via email. Just let me know when you're ready! 📧" }
                        ]);
                        setConversationState('CHATTING');
                    } catch (error) {
                        console.error("AI name extraction failed:", error);
                        setUserName(nameInput);
                        
                        // Fallback greeting + transcript reminder
                        setMessages(prev => [...prev, 
                            { role: 'model', content: `Nice to meet you, ${nameInput}! How can I help you?` },
                            { role: 'model', content: "By the way, when we're finished chatting, I can send you a transcript of our conversation via email. Just let me know when you're ready! 📧" }
                        ]);
                        setConversationState('CHATTING');
                    } finally { 
                        setIsLoading(false); 
                    }
                    return;
                }
                
                // STATE 2: Asking for email address
                if (conversationState === 'ASKING_EMAIL') {
                    if (!userEmail.trim() || !/^\S+@\S+\.\S+$/.test(userEmail)) {
                        setMessages(prev => [...prev, { 
                            role: 'model', 
                            content: "That doesn't look like a valid email. Please double-check it." 
                        }]);
                        return;
                    }
                    await sendDataToBackend();
                    return;
                }
                
                // STATE 3: Normal chatting
                if (!userInput.trim() || isLoading || conversationState !== 'CHATTING') return;
                
                const newHistory = [...messages, { role: 'user', content: userInput }];
                setMessages(newHistory); 
                setUserInput(''); 
                setIsLoading(true);
                
                try {
                    let modelResponse = await callBackend('continueChat', { 
                        history: newHistory, 
                        context: pageContext, 
                        userName: userName 
                    });
                    
                    // Check if AI indicated end of conversation
                    if (modelResponse.includes('[END_OF_CONVERSATION]')) {
                        modelResponse = modelResponse.replace('[END_OF_CONVERSATION]', '').trim();
                        setConversationState('ASKING_EMAIL');
                        setMessages(prev => [...prev, 
                            { role: 'model', content: modelResponse }, 
                            { role: 'model', content: 'I am glad I could help! If you would like a transcript, please enter your email address below.' }
                        ]);
                    } else {
                        setMessages(prev => [...prev, { role: 'model', content: modelResponse }]);
                    }
                } catch (error) {
                    console.error('Backend chat request failed:', error);
                    setMessages(prev => [...prev, { 
                        role: 'model', 
                        content: "I'm having trouble connecting right now. Please check your internet and try again." 
                    }]);
                } finally { 
                    setIsLoading(false); 
                }
            };

            // --- UI HELPERS ---
            
            // Get appropriate placeholder text based on current state
            const getInputPlaceholder = () => {
                if (!isBackendConfigured) return 'Connection not configured.';
                switch (conversationState) {
                    case 'ASKING_NAME': return 'Type your first name...';
                    case 'ASKING_EMAIL': return 'Enter your email...';
                    case 'CHATTING': return 'Ask a question...';
                    default: return '...';
                }
            };
            
            // Determine if input should be disabled
            const isInputDisabled = isLoading || !isBackendConfigured || 
                                   conversationState === 'SENDING_TRANSCRIPT' || 
                                   conversationState === 'TRANSCRIPT_SENT';

            // --- RENDER ---
            
            // When closed: show floating button
            if (!isOpen) {
                return e("button", { 
                    onClick: () => setIsOpen(true), 
                    className: "chat-float-btn",
                    "aria-label": "Open chat" 
                }, config.icon);
            }

            // When open: show full chat interface
            return e("div", { className: "chat-widget-container" },
                // Header
                e("header", { className: "chat-header" },
                    e(MaggieAvatar, { className: "chat-avatar" }),
                    e("div", { className: "chat-header-info" }, 
                        e("h2", { className: "chat-header-title" }, "Maggie"), 
                        e("p", { className: "chat-header-subtitle" }, "AI Assistant")
                    ),
                    e("button", { 
                        onClick: () => setIsOpen(false), 
                        "aria-label": "Close chat", 
                        className: "chat-close-btn" 
                    }, e(CloseIcon))
                ),
                
                // Messages area
                e("div", { className: "chat-messages" },
                    e("div", null,
                        // Render all messages
                        messages.map((msg, index) => 
                            e("div", { 
                                key: index, 
                                className: `chat-message-wrapper ${msg.role}` 
                            }, 
                                e("div", { className: `chat-message ${msg.role}` }, 
                                    msg.role === 'model' 
                                        ? e(MarkdownRenderer, { content: msg.content }) 
                                        : msg.content
                                )
                            )
                        ),
                        
                        // Loading indicator
                        isLoading && e("div", { className: "chat-loading" }, 
                            e("div", { className: "chat-loading-bubble" }, 
                                e("div", { className: "chat-loading-dots" }, 
                                    e("span", { className: "chat-loading-dot" }), 
                                    e("span", { className: "chat-loading-dot" }), 
                                    e("span", { className: "chat-loading-dot" })
                                )
                            )
                        ),
                        
                        // Invisible div for auto-scrolling
                        e("div", { ref: messagesEndRef })
                    )
                ),
                
                // Footer with input form
                e("footer", { className: "chat-footer" },
                    e("form", { onSubmit: handleSubmit, className: "chat-form" },
                        e("input", { 
                            ref: inputRef, 
                            type: conversationState === 'ASKING_EMAIL' ? 'email' : 'text', 
                            value: conversationState === 'ASKING_EMAIL' ? userEmail : userInput, 
                            onChange: handleUserInput, 
                            placeholder: getInputPlaceholder(), 
                            className: "chat-input",
                            disabled: isInputDisabled, 
                            "aria-label": "Chat input", 
                            required: isBackendConfigured 
                        }),
                        
                        e("button", { 
                            type: "submit", 
                            className: "chat-send-btn",
                            disabled: isInputDisabled || (conversationState === 'ASKING_EMAIL' ? !userEmail.trim() : !userInput.trim()), 
                            "aria-label": "Send message" 
                        }, e(SendIcon))
                    )
                )
            );
        };

        // =================================================================
        // APP INITIALIZATION - Mount the widget
        // =================================================================
        
        return e(ChatWidget, { 
            config: {
                model: window.AI_CHAT_CONFIG.model,
                icon: e(BotIcon, { width: "32", height: "32" }),
                backendUrl: window.AI_CHAT_CONFIG.backendUrl,
                nonce: window.AI_CHAT_CONFIG.nonce
            }
        });
    };
    
    // Mount React app to the DOM
    const container = document.getElementById('ai-chat-widget-root');
    const root = ReactDOM.createRoot(container);
    root.render(e(React.StrictMode, null, e(App)));
})();
</script>
HTML;
    }
});
