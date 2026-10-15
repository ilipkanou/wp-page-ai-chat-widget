<?php
/**
 * AI Webpage Chat Widget - Frontend Loader code snippet
 * v2.8-Lite   - Enhanced Security: Added Bot Trap
 * v2.8.2-Lite - Works on cached and optimised pages: gets a fresh security token when the
 *               cached one has expired, and reads its settings from data attributes, not an
 *               inline script (safe with LiteSpeed "Delay JS"). Needs backend v2.8.2-Lite.
 *               New: first-name fallback, pinned "Get this chat by email" bar, Maggie avatar.
 * 
 * by Igor Lipkanou (igor@magicquickwins.com) and Claude Sonnet 4.5
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
        
        // Step 1: Output container, with the settings as data attributes
        // CHANGED in v2.8.2: page optimisers (LiteSpeed "Delay JS") hold inline scripts back until the
        // visitor interacts, but leave attributes alone. The settings used to be an inline script.
        echo '<div id="ai-chat-widget-root" data-backend-url="' . esc_url($backend_url) . '" data-nonce="' . esc_attr($nonce) . '"></div>';
        
        // Step 2: Output import map for React
        echo '<script type="importmap">
{
  "imports": {
    "react": "https://esm.sh/react@^19.2.0",
    "react-dom/client": "https://esm.sh/react-dom@^19.2.0/client"
  }
}
</script>';
        
        // Step 3 (removed in v2.8.2): the settings are now data attributes on the container (Step 1).
        // The AI model is set on the server (GEMINI_MODEL in wp-config.php).

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
            .chat-widget-container { position: fixed; bottom: 12px; right: 12px; width: calc(100vw - 24px); max-width: 24rem; height: 60vh; max-height: 500px; background-color: #E5DDD5; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; z-index: 9999; }
            @media (min-width: 640px) { .chat-widget-container { bottom: 20px; right: 20px; width: 100%; height: 70vh; max-height: 600px; } }
            .chat-header { background-color: #005E54; color: white; padding: 12px; display: flex; align-items: center; border-radius: 8px 8px 0 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .chat-avatar { width: 40px; height: 40px; margin-right: 12px; border-radius: 50%; object-fit: cover; border: 2px solid white; flex-shrink: 0; }
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
            .chat-float-btn { position: fixed; bottom: 20px; right: 20px; background-color: #008069; color: white; padding: 3px; border-radius: 50%; box-shadow: 0 4px 12px rgba(0,0,0,0.3); border: none; cursor: pointer; transition: transform 0.2s, background-color 0.2s, box-shadow 0.2s; z-index: 9999; }
            .chat-float-btn:hover { background-color: #005E54; transform: scale(1.1); }
            .chat-float-btn:focus { outline: none; box-shadow: 0 0 0 2px #005E54, 0 4px 12px rgba(0,0,0,0.3); }
            .chat-float-btn img { display: block; width: 56px; height: 56px; border-radius: 50%; }
            /* Pinned transcript bar - NEW in v2.8.2 */
            .chat-pin { background-color: #FFF8E1; border-bottom: 1px solid #F3E2A9; padding: 6px 12px; }
            .chat-pin-toggle { width: 100%; background: none; border: none; padding: 4px 0; text-align: left; font-size: 13px; font-weight: 600; color: #005E54; cursor: pointer; }
            .chat-pin-toggle:disabled { cursor: default; }
            .chat-pin-toggle svg { vertical-align: -3px; margin-right: 6px; }
            .chat-pin-form { display: flex; align-items: center; gap: 6px; }
            .chat-pin-input { flex: 1; min-width: 0; padding: 6px 12px; border: 1px solid #D1D5DB; border-radius: 16px; font-size: 16px; outline: none; }
            .chat-pin-input:focus { border-color: #005E54; box-shadow: 0 0 0 1px #005E54; }
            .chat-pin-send { padding: 7px 14px; background-color: #008069; color: white; border: none; border-radius: 16px; font-size: 13px; font-weight: 600; cursor: pointer; }
            .chat-pin-send:disabled { background-color: #9CA3AF; cursor: not-allowed; }
            .chat-pin-close { background: none; border: none; color: #6B7280; cursor: pointer; font-size: 14px; padding: 4px; }
            .chat-pin-error { color: #B91C1C; font-size: 12px; margin-top: 4px; }
        `;
        document.head.appendChild(style);
    };
    
    // Inject styles immediately
    injectStyles();

    // =================================================================
    // MAGGIE AVATAR - NEW in v2.8.2 (inline image, no external request)
    // =================================================================
    const MAGGIE_AVATAR = 'data:image/webp;base64,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';

    // =================================================================
    // NAME FALLBACK - NEW in v2.8.2
    // Finds the first name in a message like "Hello. My name is Bob."
    // Used only when the AI is unavailable. Returns '' when no name is found.
    // =================================================================
    const NOT_NAMES = new Set(['hi', 'hello', 'hey', 'hiya', 'there', 'good', 'morning', 'afternoon', 'evening', 'thanks', 'thank', 'me', 'myself',
        'interested', 'looking', 'here', 'just', 'not', 'from', 'in', 'a', 'an', 'the', 'so', 'very', 'wondering', 'trying', 'curious',
        'new', 'fine', 'ok', 'okay', 'back', 'still', 'ready', 'sure', 'yes', 'no']);
    const TITLES = new Set(['mr', 'mrs', 'ms', 'miss', 'dr', 'prof']);

    const extractFirstName = (text) => {
        const intro = text.match(/\b(?:my name is|my name[’']s|name is|i am|i[’']m|im|call me|this is|it[’']s|it is)\s+(.+)/i);
        const words = (intro ? intro[1] : text)
            .split(/\s+/)
            .map(w => w.replace(/[^\p{L}’'-]/gu, ''))
            .filter(w => w && !TITLES.has(w.toLowerCase()));
        // Without an intro phrase, accept only a short answer such as "Mike" or "Hi, Bob Smith"
        const candidates = intro ? words : words.filter(w => !NOT_NAMES.has(w.toLowerCase()));
        if (!intro && (candidates.length > 2 || text.includes('?'))) return '';
        const first = candidates[0] || '';
        if (first.length < 2 || first.length > 20 || NOT_NAMES.has(first.toLowerCase())) return '';
        // "bob" or "BOB" becomes "Bob"; a mixed-case name such as "McKenzie" stays as typed
        return (first === first.toLowerCase() || first === first.toUpperCase())
            ? first.charAt(0).toUpperCase() + first.slice(1).toLowerCase()
            : first;
    };

    const App = () => {
        
        // =================================================================
        // ICON COMPONENTS - SVG icons for UI
        // =================================================================
        
        const CloseIcon = (props) => e("svg", { ...props, xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", fill: "none", stroke: "currentColor", strokeWidth: "2", strokeLinecap: "round", strokeLinejoin: "round", width: "24", height: "24" }, e("line", { x1: "18", y1: "6", x2: "6", y2: "18" }), e("line", { x1: "6", y1: "6", x2: "18", y2: "18" }));

        // NEW in v2.8.2: envelope icon for the pinned transcript bar
        const MailIcon = (props) => e("svg", { ...props, xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", fill: "none", stroke: "currentColor", strokeWidth: "2", strokeLinecap: "round", strokeLinejoin: "round", width: "16", height: "16", "aria-hidden": "true" }, e("rect", { x: "2", y: "4", width: "20", height: "16", rx: "2" }), e("path", { d: "M22 7l-10 6L2 7" }));
        
        const SendIcon = (props) => e("svg", { ...props, xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", fill: "none", stroke: "currentColor", strokeWidth: "2", strokeLinecap: "round", strokeLinejoin: "round", width: "24", height: "24" }, e("line", { x1: "22", y1: "2", x2: "11", y2: "13" }), e("polygon", { points: "22 2 15 22 11 13 2 9 22 2" }));
        
        const MaggieAvatar = (props) => e("img", { ...props, src: MAGGIE_AVATAR, alt: "" });  // v2.8.2: Maggie's picture
        
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
            // NEW in v2.8.2: pinned "Get this chat by email" bar
            const [barOpen, setBarOpen] = React.useState(false);
            const [barEmail, setBarEmail] = React.useState('');
            const [barStatus, setBarStatus] = React.useState('idle');  // idle | invalid | waiting | sending | sent | error
            const [transcriptEmail, setTranscriptEmail] = React.useState('');
            const transcriptEmailRef = React.useRef('');  // the same email, readable inside async handlers
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
            
            // NEW in v2.8.2: security token refresh
            // The page cache can serve this page for days, but the security token in it
            // expires after 12-24 hours. WordPress then answers 403
            // "Cookie check failed". On that answer, get a fresh security token and retry once.
            const nonceRef = React.useRef(config.nonce);

            const refreshNonce = async () => {
                const response = await fetch(config.backendUrl + '/nonce', {
                    method: 'POST',
                    credentials: 'omit'
                });
                if (!response.ok) throw new Error(`Nonce refresh failed: HTTP ${response.status}`);
                nonceRef.current = (await response.json()).nonce;
            };

            // POST to a backend endpoint. No cookies are sent, so WordPress treats
            // every visitor as logged out and the security token always matches.
            const postToBackend = async (endpoint, body) => {
                const send = () => fetch(config.backendUrl + endpoint, {
                    method: 'POST',
                    credentials: 'omit',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonceRef.current
                    },
                    body: JSON.stringify(body)
                });

                let response = await send();
                if (response.status === 403) {
                    const errorData = await response.clone().json().catch(() => ({}));
                    if (errorData.code === 'rest_cookie_invalid_nonce' || errorData.code === 'rest_forbidden') {
                        await refreshNonce();
                        response = await send();
                    }
                }
                return response;
            };

            // Generic backend API call function
            const callBackend = async (action, payload) => {
                try {
                    const response = await postToBackend('/chat', {
                        action,
                        payload
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
            // CHANGED in v2.8.2: used by the end of the chat (mode 'final') and by the
            // pinned bar (mode 'bar': sends the chat so far, and the visitor keeps chatting)
            const sendTranscript = async (email, history, mode, intro) => {
                const isFinal = mode === 'final';
                if (isFinal) {
                    setIsLoading(true);
                    setConversationState('SENDING_TRANSCRIPT');
                    setMessages(prev => [...prev, { role: 'model', content: intro }]);
                } else {
                    setBarStatus('sending');
                }

                try {
                    // Generate summary
                    const summary = await callBackend('summarizeConversation', { history });
                    if (isFinal) {
                        setMessages(prev => [...prev, { role: 'model', content: "Okay, sending the transcript..." }]);
                    }

                    // Submit to /lead endpoint
                    // 'website' is a bot trap field. It is NEVER filled by real visitors (it's not visible).
                    // Automated bots that fill all forms will fill this field, and the backend will reject them
                    const response = await postToBackend('/lead', {
                        name: userName,
                        email: email,
                        transcript: history,
                        summary: summary,
                        website: ''  // Bot trap field - should always be empty for real visitors
                    });

                    if (!response.ok) throw new Error('Failed to send transcript');
                    await response.json();

                    rememberEmail(email);
                    setBarStatus('sent');
                    if (isFinal) {
                        setMessages(prev => [...prev, { role: 'model', content: "Done! The transcript has been sent. Feel free to start a new chat." }]);
                        setConversationState('TRANSCRIPT_SENT');
                    } else {
                        setMessages(prev => [...prev, { role: 'model', content: `Done! I sent our chat so far to ${email}. Feel free to keep asking! 😊` }]);
                    }
                } catch (error) {
                    console.error('Failed to send transcript:', error);
                    if (isFinal) {
                        setMessages(prev => [...prev, { role: 'model', content: "I'm having trouble sending the transcript right now. Please try again in a moment." }]);
                        setUserEmail(email);
                        setConversationState('ASKING_EMAIL');
                    } else {
                        setBarStatus('error');
                    }
                } finally {
                    if (isFinal) setIsLoading(false);
                }
            };

            // --- PINNED TRANSCRIPT BAR (NEW in v2.8.2) ---

            const rememberEmail = (email) => {
                transcriptEmailRef.current = email;
                setTranscriptEmail(email);
            };

            // The backend sends a transcript only for a real conversation.
            // Enough = the visitor asked at least one question and Maggie answered it.
            const hasEnoughChat = (history) =>
                history.filter(m => m.role === 'user').length >= 2 && history[history.length - 1].role === 'model';

            const handleBarSubmit = (ev) => {
                ev.preventDefault();
                const email = barEmail.trim();
                if (!/^\S+@\S+\.\S+$/.test(email)) {
                    setBarStatus('invalid');
                    return;
                }
                rememberEmail(email);
                setBarOpen(false);
                setBarStatus('waiting');  // the effect below sends it as soon as the chat is long enough
            };

            // Send a waiting transcript once the chat is long enough and Maggie is not busy
            React.useEffect(() => {
                if (barStatus === 'waiting' && !isLoading && hasEnoughChat(messages)) {
                    sendTranscript(transcriptEmailRef.current, messages, 'bar');
                }
            }, [barStatus, isLoading, messages]);

            const getBarText = () => {
                switch (barStatus) {
                    case 'waiting': return `✓ I'll send this chat to ${transcriptEmail} after my next answer`;
                    case 'sending': return `Sending this chat to ${transcriptEmail}...`;
                    case 'sent': return conversationState === 'TRANSCRIPT_SENT'
                        ? `✓ Transcript sent to ${transcriptEmail}`
                        : `✓ Sent to ${transcriptEmail} · Send again ▸`;
                    case 'error': return '⚠ Could not send the chat. Tap to try again';
                    default: return 'Get this chat by email ▸';
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
                        if (!name || !greeting) throw new Error('Incomplete AI reply');  // v2.8.2: use the fallback below
                        setUserName(name);
                        
                        // Add greeting + transcript reminder
                        setMessages(prev => [...prev, 
                            { role: 'model', content: greeting },
                            { role: 'model', content: "By the way, when we're finished chatting, I can send you a transcript of our conversation via email. Just let me know when you're ready! 📧" }
                        ]);
                        setConversationState('CHATTING');
                    } catch (error) {
                        console.error("AI name extraction failed:", error);
                        // FIXED in v2.8.2: use the first name found in the message, not the whole message
                        const fallbackName = extractFirstName(nameInput);
                        setUserName(fallbackName || 'friend');
                        
                        // Fallback greeting + transcript reminder
                        setMessages(prev => [...prev, 
                            { role: 'model', content: fallbackName ? `Nice to meet you, ${fallbackName}! How can I help you?` : 'Nice to meet you! How can I help you?' },
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
                    await sendTranscript(userEmail.trim(), messages, 'final', "Got it. I'm preparing a summary...");
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
                        const knownEmail = transcriptEmailRef.current;
                        if (knownEmail) {
                            // NEW in v2.8.2: the visitor gave an email in the pinned bar, so send the full transcript there
                            setMessages(prev => [...prev, { role: 'model', content: modelResponse }]);
                            await sendTranscript(knownEmail, [...newHistory, { role: 'model', content: modelResponse }], 'final',
                                `I'm sending the full transcript to ${knownEmail}...`);
                        } else {
                            setConversationState('ASKING_EMAIL');
                            setMessages(prev => [...prev,
                                { role: 'model', content: modelResponse },
                                { role: 'model', content: 'I am glad I could help! If you would like a transcript, please enter your email address below.' }
                            ]);
                        }
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
                }, e(MaggieAvatar));  // v2.8.2: Maggie's picture instead of the robot icon
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

                // NEW in v2.8.2: pinned "Get this chat by email" bar, shown once Maggie has mentioned the transcript
                conversationState !== 'ASKING_NAME' && e("div", { className: "chat-pin" },
                    barOpen
                        ? e("form", { onSubmit: handleBarSubmit, className: "chat-pin-form" },
                            e("input", {
                                type: "email",
                                value: barEmail,
                                onChange: (ev) => { setBarEmail(ev.target.value); if (barStatus === 'invalid') setBarStatus('idle'); },
                                placeholder: "your@email.com",
                                className: "chat-pin-input",
                                "aria-label": "Your email for the chat transcript",
                                autoFocus: true
                            }),
                            e("button", { type: "submit", className: "chat-pin-send", disabled: !barEmail.trim() }, "Send"),
                            e("button", { type: "button", className: "chat-pin-close", onClick: () => setBarOpen(false), "aria-label": "Close" }, "✕")
                        )
                        : e("button", {
                            type: "button",
                            className: "chat-pin-toggle",
                            onClick: () => { setBarEmail(transcriptEmail || barEmail); setBarOpen(true); },
                            disabled: barStatus === 'waiting' || barStatus === 'sending' ||
                                      conversationState === 'SENDING_TRANSCRIPT' || conversationState === 'TRANSCRIPT_SENT'
                        }, barStatus === 'idle' && e(MailIcon), getBarText()),
                    barOpen && barStatus === 'invalid' && e("div", { className: "chat-pin-error" }, "Please check the email address.")
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
        
        // v2.8.2: settings come from the container's data attributes (see Step 1)
        const settings = document.getElementById('ai-chat-widget-root').dataset;
        return e(ChatWidget, {
            config: {
                backendUrl: settings.backendUrl,
                nonce: settings.nonce
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
