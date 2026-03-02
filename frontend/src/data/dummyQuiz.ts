export interface Question {
  id: number;
  question: string;
  options: string[];
  correctAnswer: number;
  category: string;
  difficulty: 'easy' | 'medium' | 'hard';
}

export interface Quiz {
  id: string;
  title: string;
  description: string;
  totalQuestions: number;
  timeLimit: number; // in seconds
  questions: Question[];
}

export const dummyQuiz: Quiz = {
  id: 'quiz_001',
  title: 'General Knowledge Challenge',
  description: 'Test your knowledge across various topics',
  totalQuestions: 10,
  timeLimit: 600, // 10 minutes
  questions: [
    {
      id: 1,
      question: 'What is the capital of France?',
      options: ['London', 'Berlin', 'Paris', 'Madrid'],
      correctAnswer: 2,
      category: 'Geography',
      difficulty: 'easy',
    },
    {
      id: 2,
      question: 'Which planet is known as the Red Planet?',
      options: ['Venus', 'Mars', 'Jupiter', 'Saturn'],
      correctAnswer: 1,
      category: 'Science',
      difficulty: 'easy',
    },
    {
      id: 3,
      question: 'Who wrote Romeo and Juliet?',
      options: ['Jane Austen', 'William Shakespeare', 'Charles Dickens', 'Mark Twain'],
      correctAnswer: 1,
      category: 'Literature',
      difficulty: 'easy',
    },
    {
      id: 4,
      question: 'What is the chemical symbol for Gold?',
      options: ['Go', 'Gd', 'Au', 'Ag'],
      correctAnswer: 2,
      category: 'Chemistry',
      difficulty: 'medium',
    },
    {
      id: 5,
      question: 'In which year did the Titanic sink?',
      options: ['1912', '1915', '1920', '1905'],
      correctAnswer: 0,
      category: 'History',
      difficulty: 'medium',
    },
    {
      id: 6,
      question: 'What is the smallest prime number?',
      options: ['0', '1', '2', '3'],
      correctAnswer: 2,
      category: 'Mathematics',
      difficulty: 'easy',
    },
    {
      id: 7,
      question: 'Which country has the most populated city in the world?',
      options: ['China', 'Japan', 'India', 'Indonesia'],
      correctAnswer: 1,
      category: 'Geography',
      difficulty: 'hard',
    },
    {
      id: 8,
      question: 'What is the speed of light?',
      options: ['300,000 m/s', '3,000 km/s', '30,000 km/s', '300 km/s'],
      correctAnswer: 1,
      category: 'Physics',
      difficulty: 'medium',
    },
    {
      id: 9,
      question: 'Who painted the Mona Lisa?',
      options: ['Van Gogh', 'Leonardo da Vinci', 'Michelangelo', 'Raphael'],
      correctAnswer: 1,
      category: 'Art',
      difficulty: 'easy',
    },
    {
      id: 10,
      question: 'What is the powerhouse of the cell?',
      options: ['Nucleus', 'Mitochondria', 'Ribosome', 'Golgi Apparatus'],
      correctAnswer: 1,
      category: 'Biology',
      difficulty: 'medium',
    },
  ],
};
