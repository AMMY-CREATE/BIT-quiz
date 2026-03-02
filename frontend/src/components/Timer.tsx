import React, { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { AlertCircle } from 'lucide-react';

interface TimerProps {
  timeRemaining: number;
  totalTime: number;
  onTimeEnd?: () => void;
}

export const Timer: React.FC<TimerProps> = ({ timeRemaining, totalTime, onTimeEnd }) => {
  const [isWarning, setIsWarning] = useState(false);
  const percentage = (timeRemaining / totalTime) * 100;
  
  const minutes = Math.floor(timeRemaining / 60);
  const seconds = timeRemaining % 60;
  
  useEffect(() => {
    if (timeRemaining <= 60) {
      setIsWarning(true);
    } else {
      setIsWarning(false);
    }
    
    if (timeRemaining <= 0 && onTimeEnd) {
      onTimeEnd();
    }
  }, [timeRemaining, onTimeEnd]);
  
  const getTimerColor = () => {
    if (timeRemaining <= 30) return 'from-red-500 to-red-600';
    if (timeRemaining <= 60) return 'from-yellow-500 to-yellow-600';
    return 'from-blue-500 to-blue-600';
  };
  
  return (
    <motion.div
      initial={{ opacity: 0, scale: 0.9 }}
      animate={{ opacity: 1, scale: 1 }}
      className="flex items-center gap-3"
    >
      <div className="relative w-20 h-20">
        <svg className="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
          <circle
            cx="50"
            cy="50"
            r="45"
            fill="none"
            stroke="rgba(255,255,255,0.1)"
            strokeWidth="4"
          />
          <motion.circle
            cx="50"
            cy="50"
            r="45"
            fill="none"
            stroke="url(#timerGradient)"
            strokeWidth="4"
            strokeDasharray={`${2 * Math.PI * 45}`}
            initial={{ strokeDashoffset: `${2 * Math.PI * 45}` }}
            animate={{ strokeDashoffset: `${2 * Math.PI * 45 * (1 - percentage / 100)}` }}
            transition={{ duration: 0.3 }}
            strokeLinecap="round"
          />
          <defs>
            <linearGradient id="timerGradient" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stopColor={isWarning ? '#ef4444' : '#3b82f6'} />
              <stop offset="100%" stopColor={isWarning ? '#dc2626' : '#1d4ed8'} />
            </linearGradient>
          </defs>
        </svg>
        <div className="absolute inset-0 flex items-center justify-center">
          <span className={`text-sm font-bold ${isWarning ? 'text-red-400' : 'text-blue-400'}`}>
            {String(minutes).padStart(2, '0')}:{String(seconds).padStart(2, '0')}
          </span>
        </div>
      </div>
      
      {isWarning && (
        <motion.div
          animate={{ scale: [1, 1.1, 1] }}
          transition={{ duration: 1, repeat: Infinity }}
          className="flex items-center gap-2 text-red-400"
        >
          <AlertCircle size={16} />
          <span className="text-xs font-semibold">Time Ending</span>
        </motion.div>
      )}
    </motion.div>
  );
};
