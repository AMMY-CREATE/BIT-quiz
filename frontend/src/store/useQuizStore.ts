import { create } from 'zustand';
import { Question } from '../data/dummyQuiz';

export interface Answer {
  questionId: number;
  selectedAnswer: number;
  isCorrect: boolean;
  timeSpent: number; // in seconds
}

export interface QuizState {
  // Basic info
  studentName: string;
  studentId: string;
  
  // Quiz progress
  currentQuestionIndex: number;
  answers: Answer[];
  startTime: number | null;
  timeRemaining: number;
  isQuizActive: boolean;
  
  // Results
  score: number;
  totalQuestions: number;
  
  // Actions
  initializeQuiz: (totalQuestions: number, timeLimit: number) => void;
  setStudentInfo: (name: string, id: string) => void;
  startQuiz: () => void;
  endQuiz: () => void;
  submitAnswer: (questionId: number, selectedAnswer: number, isCorrect: boolean, timeSpent: number) => void;
  goToNextQuestion: () => void;
  goToPreviousQuestion: () => void;
  goToQuestion: (index: number) => void;
  updateTimeRemaining: (timeRemaining: number) => void;
  resetQuiz: () => void;
  getQuestionProgress: () => { answered: number; total: number };
  getResults: () => { answers: Answer[]; score: number };
}

export const useQuizStore = create<QuizState>((set, get) => ({
  studentName: '',
  studentId: '',
  
  currentQuestionIndex: 0,
  answers: [],
  startTime: null,
  timeRemaining: 0,
  isQuizActive: false,
  
  score: 0,
  totalQuestions: 0,
  
  initializeQuiz: (totalQuestions, timeLimit) => {
    set({
      totalQuestions,
      timeRemaining: timeLimit,
      currentQuestionIndex: 0,
      answers: [],
      score: 0,
    });
  },
  
  setStudentInfo: (name, id) => {
    set({
      studentName: name,
      studentId: id,
    });
  },
  
  startQuiz: () => {
    set({
      isQuizActive: true,
      startTime: Date.now(),
    });
  },
  
  endQuiz: () => {
    set({ isQuizActive: false });
  },
  
  submitAnswer: (questionId, selectedAnswer, isCorrect, timeSpent) => {
    const state = get();
    const newAnswers = [...state.answers];
    
    // Replace existing answer if it exists, otherwise add new
    const existingIndex = newAnswers.findIndex(a => a.questionId === questionId);
    if (existingIndex !== -1) {
      newAnswers[existingIndex] = {
        questionId,
        selectedAnswer,
        isCorrect,
        timeSpent,
      };
    } else {
      newAnswers.push({
        questionId,
        selectedAnswer,
        isCorrect,
        timeSpent,
      });
    }
    
    const newScore = newAnswers.filter(a => a.isCorrect).length;
    
    set({
      answers: newAnswers,
      score: newScore,
    });
  },
  
  goToNextQuestion: () => {
    const state = get();
    if (state.currentQuestionIndex < state.totalQuestions - 1) {
      set({ currentQuestionIndex: state.currentQuestionIndex + 1 });
    }
  },
  
  goToPreviousQuestion: () => {
    const state = get();
    if (state.currentQuestionIndex > 0) {
      set({ currentQuestionIndex: state.currentQuestionIndex - 1 });
    }
  },
  
  goToQuestion: (index) => {
    const state = get();
    if (index >= 0 && index < state.totalQuestions) {
      set({ currentQuestionIndex: index });
    }
  },
  
  updateTimeRemaining: (timeRemaining) => {
    set({ timeRemaining: Math.max(0, timeRemaining) });
  },
  
  resetQuiz: () => {
    set({
      studentName: '',
      studentId: '',
      currentQuestionIndex: 0,
      answers: [],
      startTime: null,
      timeRemaining: 0,
      isQuizActive: false,
      score: 0,
      totalQuestions: 0,
    });
  },
  
  getQuestionProgress: () => {
    const state = get();
    return {
      answered: state.answers.length,
      total: state.totalQuestions,
    };
  },
  
  getResults: () => {
    const state = get();
    return {
      answers: state.answers,
      score: state.score,
    };
  },
}));
