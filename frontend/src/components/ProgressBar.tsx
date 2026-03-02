import React from 'react';
import { motion } from 'framer-motion';

interface ProgressBarProps {
  current: number;
  total: number;
  showText?: boolean;
}

export const ProgressBar: React.FC<ProgressBarProps> = ({ current, total, showText = true }) => {
  const percentage = (current / total) * 100;
  
  return (
    <motion.div
      initial={{ opacity: 0, y: -10 }}
      animate={{ opacity: 1, y: 0 }}
      className="w-full"
    >
      <div className="flex items-center justify-between mb-2">
        <h3 className="text-sm font-semibold text-slate-200">Progress</h3>
        {showText && (
          <span className="text-xs text-slate-400">
            {current} of {total}
          </span>
        )}
      </div>
      
      <div className="relative h-2 bg-slate-700 rounded-full overflow-hidden backdrop-blur-sm">
        <motion.div
          initial={{ width: 0 }}
          animate={{ width: `${percentage}%` }}
          transition={{ duration: 0.5, ease: 'easeOut' }}
          className="h-full bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500 rounded-full"
        />
      </div>
      
      <div className="mt-2 flex gap-1 flex-wrap">
        {Array.from({ length: total }).map((_, i) => (
          <motion.div
            key={i}
            initial={{ scale: 0 }}
            animate={{ scale: 1 }}
            transition={{ delay: i * 0.05 }}
            className={`h-1.5 w-1.5 rounded-full ${
              i < current ? 'bg-blue-500' : 'bg-slate-600'
            }`}
          />
        ))}
      </div>
    </motion.div>
  );
};
