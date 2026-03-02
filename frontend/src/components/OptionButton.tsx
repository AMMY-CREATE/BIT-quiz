import React from 'react';
import { motion } from 'framer-motion';
import { CheckCircle, XCircle } from 'lucide-react';

interface OptionButtonProps {
  option: string;
  index: number;
  isSelected: boolean;
  isCorrect?: boolean;
  isAnswered?: boolean;
  onClick?: () => void;
  disabled?: boolean;
}

export const OptionButton: React.FC<OptionButtonProps> = ({
  option,
  index,
  isSelected,
  isCorrect,
  isAnswered,
  onClick,
  disabled = false,
}) => {
  const getBackgroundColor = () => {
    if (!isAnswered) {
      return isSelected
        ? 'bg-gradient-to-r from-blue-600 to-blue-700 border-blue-400'
        : 'bg-slate-700 hover:bg-slate-600 border-slate-600';
    }
    
    if (isSelected && isCorrect) return 'bg-gradient-to-r from-green-600 to-green-700 border-green-400';
    if (isSelected && !isCorrect) return 'bg-gradient-to-r from-red-600 to-red-700 border-red-400';
    if (!isSelected && isCorrect) return 'bg-gradient-to-r from-green-500 to-green-600 border-green-400';
    return 'bg-slate-700 border-slate-600';
  };
  
  const getTextColor = () => {
    if (!isAnswered && !isSelected) return 'text-slate-300';
    return 'text-white';
  };
  
  return (
    <motion.button
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      whileHover={!disabled && !isAnswered ? { scale: 1.02 } : {}}
      whileTap={!disabled && !isAnswered ? { scale: 0.98 } : {}}
      transition={{ delay: index * 0.1 }}
      onClick={onClick}
      disabled={disabled || isAnswered}
      className={`
        w-full p-4 text-left rounded-lg border-2 transition-all
        ${getBackgroundColor()} ${getTextColor()}
        ${disabled || isAnswered ? 'cursor-not-allowed' : 'cursor-pointer'}
        flex items-center gap-3 backdrop-blur-sm
      `}
    >
      <span className="flex-shrink-0 w-6 h-6 rounded-full border-2 border-current flex items-center justify-center text-sm font-bold">
        {String.fromCharCode(65 + index)}
      </span>
      
      <span className="flex-grow font-medium">{option}</span>
      
      {isAnswered && isSelected && isCorrect && (
        <CheckCircle size={20} className="text-green-300 flex-shrink-0" />
      )}
      {isAnswered && isSelected && !isCorrect && (
        <XCircle size={20} className="text-red-300 flex-shrink-0" />
      )}
      {isAnswered && !isSelected && isCorrect && (
        <CheckCircle size={20} className="text-green-200 flex-shrink-0 opacity-70" />
      )}
    </motion.button>
  );
};
