import React from 'react';
import { motion } from 'framer-motion';
import { Question } from '../data/dummyQuiz';
import { OptionButton } from './OptionButton';

interface QuestionCardProps {
  question: Question;
  questionNumber: number;
  selectedAnswer: number | null;
  isAnswered: boolean;
  onSelectAnswer: (optionIndex: number) => void;
}

export const QuestionCard: React.FC<QuestionCardProps> = ({
  question,
  questionNumber,
  selectedAnswer,
  isAnswered,
  onSelectAnswer,
}) => {
  const getDifficultyColor = () => {
    switch (question.difficulty) {
      case 'easy':
        return 'bg-green-500/20 text-green-300 border-green-500/30';
      case 'medium':
        return 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30';
      case 'hard':
        return 'bg-red-500/20 text-red-300 border-red-500/30';
    }
  };
  
  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, y: -20 }}
      transition={{ duration: 0.3 }}
      className="bg-slate-800/50 backdrop-blur-md rounded-2xl border border-slate-700 p-8 space-y-6"
    >
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <h2 className="text-3xl font-bold text-white mb-2">
            Question {questionNumber}
          </h2>
          <p className="text-slate-400 text-sm">Category: {question.category}</p>
        </div>
        <motion.div
          initial={{ scale: 0 }}
          animate={{ scale: 1 }}
          className={`px-3 py-1 rounded-full text-xs font-semibold border ${getDifficultyColor()}`}
        >
          {question.difficulty.charAt(0).toUpperCase() + question.difficulty.slice(1)}
        </motion.div>
      </div>
      
      {/* Question text */}
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ delay: 0.1 }}
        className="bg-gradient-to-r from-blue-500/10 to-purple-500/10 border border-blue-400/30 rounded-lg p-5"
      >
        <p className="text-xl font-semibold text-white leading-relaxed">
          {question.question}
        </p>
      </motion.div>
      
      {/* Options */}
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ delay: 0.2 }}
        className="space-y-3"
      >
        {question.options.map((option, index) => (
          <OptionButton
            key={index}
            option={option}
            index={index}
            isSelected={selectedAnswer === index}
            isCorrect={index === question.correctAnswer}
            isAnswered={isAnswered}
            onClick={() => !isAnswered && onSelectAnswer(index)}
            disabled={isAnswered}
          />
        ))}
      </motion.div>
      
      {/* Feedback message */}
      {isAnswered && (
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className={`p-4 rounded-lg border ${
            selectedAnswer === question.correctAnswer
              ? 'bg-green-500/10 border-green-400/30 text-green-300'
              : 'bg-red-500/10 border-red-400/30 text-red-300'
          }`}
        >
          <p className="font-medium">
            {selectedAnswer === question.correctAnswer
              ? '✓ Correct! Well done!'
              : '✗ Incorrect. The correct answer has been highlighted.'}
          </p>
        </motion.div>
      )}
    </motion.div>
  );
};
