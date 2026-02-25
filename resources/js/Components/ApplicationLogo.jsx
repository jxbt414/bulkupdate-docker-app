export default function ApplicationLogo({ className = '' }) {
    return (
        <div className={`flex items-center ${className}`}>
            <div className="relative w-10 h-10">
                {/* Outer circle */}
                <div className="absolute inset-0 rounded-full bg-gradient-to-r from-primary-600 to-primary-400 animate-pulse"></div>
                {/* Inner circle */}
                <div className="absolute inset-1 rounded-full bg-white"></div>
                {/* Center icon */}
                <div className="absolute inset-0 flex items-center justify-center">
                    <svg
                        className="w-6 h-6 text-primary-600"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                        />
                    </svg>
                </div>
            </div>
            <span className="ml-3 text-xl font-semibold text-gray-900">GAM Bulk Update</span>
        </div>
    );
}
