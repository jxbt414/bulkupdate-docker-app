import { useState, useRef } from 'react';
import axios from 'axios';
import { toast } from 'react-hot-toast';
import html2canvas from 'html2canvas-pro';

export default function FeedbackChatbot() {
    const [isOpen, setIsOpen] = useState(false);
    const [messages, setMessages] = useState([
        { type: 'bot', content: 'Hi! 👋 How can I help you today?' }
    ]);
    const [inputMessage, setInputMessage] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const chatRef = useRef(null);

    const handleScreenshot = async () => {
        let originalDisplay = null;
        let chatbot = null;
        
        try {
            setIsLoading(true);
            
            // Hide the chatbot temporarily
            chatbot = document.querySelector('.fixed.bottom-4.right-4');
            if (chatbot) {
                originalDisplay = chatbot.style.display;
                chatbot.style.display = 'none';
            }

            // Take screenshot using native Web Screenshot API
            const stream = await navigator.mediaDevices.getDisplayMedia({ 
                preferCurrentTab: true,
                video: {
                    displaySurface: "browser"
                }
            });

            // Create a video element to capture the stream
            const video = document.createElement("video");
            video.srcObject = stream;
            await video.play();

            // Create a canvas to draw the video frame
            const canvas = document.createElement("canvas");
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            // Draw the video frame to the canvas
            const ctx = canvas.getContext("2d");
            ctx.drawImage(video, 0, 0);

            // Stop all tracks
            stream.getTracks().forEach(track => track.stop());

            // Convert to base64
            const screenshot = canvas.toDataURL('image/png', 1.0);
            
            // Send screenshot to backend
            const response = await axios.post('/feedback', {
                message: 'Screenshot captured:',
                type: 'user',
                screenshot: screenshot
            });

            // Add screenshot message locally
            setMessages(prev => [...prev, 
                { 
                    type: 'user', 
                    content: 'Screenshot captured successfully:', 
                    isScreenshot: true, 
                    image: screenshot 
                }
            ]);

            toast.success('Screenshot captured successfully!');
        } catch (error) {
            console.error('Screenshot error:', error);
            
            // Show user-friendly error message based on error type
            let errorMessage = 'Failed to take screenshot. ';
            if (error.name === 'NotAllowedError') {
                errorMessage += 'Please allow screen capture permission.';
            } else if (error.name === 'NotReadableError') {
                errorMessage += 'Could not capture screen content.';
            } else {
                errorMessage += 'Please try again.';
            }
            
            toast.error(errorMessage);
            
            setMessages(prev => [...prev, 
                { 
                    type: 'bot', 
                    content: errorMessage
                }
            ]);
        } finally {
            // Restore chatbot visibility
            if (chatbot && originalDisplay !== null) {
                chatbot.style.display = originalDisplay;
            }
            setIsLoading(false);
            setIsOpen(true);
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!inputMessage.trim()) return;

        try {
            setIsLoading(true);
            
            // Add user message locally
            const userMessage = { type: 'user', content: inputMessage };
            setMessages(prev => [...prev, userMessage]);
            setInputMessage('');

            // Send user message to backend
            await axios.post('/feedback', {
                message: inputMessage,
                type: 'user'
            });

            // Add and send bot response
            const botResponse = { 
                type: 'bot', 
                content: 'Thanks for your feedback! Our team will look into this.' 
            };
            setMessages(prev => [...prev, botResponse]);

            await axios.post('/feedback', {
                message: botResponse.content,
                type: 'bot'
            });

            // Scroll to bottom
            if (chatRef.current) {
                chatRef.current.scrollTop = chatRef.current.scrollHeight;
            }
        } catch (error) {
            console.error('Failed to send feedback:', error);
            toast.error('Failed to send feedback. Please try again.');
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="fixed bottom-4 right-4 z-50">
            {/* Chat Button */}
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="btn btn-circle btn-primary shadow-lg"
            >
                {isOpen ? (
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                ) : (
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                )}
            </button>

            {/* Chat Window */}
            {isOpen && (
                <div className="absolute bottom-16 right-0 w-96 bg-white rounded-lg shadow-xl border border-gray-200 overflow-hidden">
                    {/* Header */}
                    <div className="bg-primary text-white p-4 flex justify-between items-center">
                        <h3 className="font-medium">Feedback Assistant</h3>
                        <button
                            onClick={handleScreenshot}
                            className={`btn btn-ghost btn-sm ${isLoading ? 'loading' : ''}`}
                            disabled={isLoading}
                        >
                            {isLoading ? (
                                <span className="loading loading-spinner loading-sm"></span>
                            ) : (
                                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            )}
                        </button>
                    </div>

                    {/* Messages */}
                    <div 
                        ref={chatRef}
                        className="h-96 overflow-y-auto p-4 space-y-4"
                    >
                        {messages.map((message, index) => (
                            <div
                                key={index}
                                className={`flex ${message.type === 'user' ? 'justify-end' : 'justify-start'}`}
                            >
                                <div
                                    className={`max-w-[80%] rounded-lg p-3 ${
                                        message.type === 'user'
                                            ? 'bg-primary text-white'
                                            : 'bg-gray-100 text-gray-800'
                                    }`}
                                >
                                    {message.isScreenshot ? (
                                        <div className="space-y-2">
                                            <p>{message.content}</p>
                                            <img 
                                                src={message.image} 
                                                alt="Screenshot" 
                                                className="rounded-lg border border-gray-200"
                                            />
                                        </div>
                                    ) : (
                                        <p>{message.content}</p>
                                    )}
                                </div>
                            </div>
                        ))}
                        {isLoading && (
                            <div className="flex justify-start">
                                <div className="bg-gray-100 rounded-lg p-3">
                                    <span className="loading loading-dots loading-sm"></span>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Input */}
                    <form onSubmit={handleSubmit} className="border-t border-gray-200 p-4">
                        <div className="flex gap-2">
                            <input
                                type="text"
                                value={inputMessage}
                                onChange={(e) => setInputMessage(e.target.value)}
                                placeholder="Type your message..."
                                className="input input-bordered flex-1"
                            />
                            <button
                                type="submit"
                                className="btn btn-primary"
                                disabled={!inputMessage.trim() || isLoading}
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </div>
    );
} 