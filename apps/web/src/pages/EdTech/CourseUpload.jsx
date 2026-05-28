import { useMemo, useState } from "react";
import {
  ArrowLeft,
  CheckCircle2,
  ChevronDown,
  ChevronUp,
  GripVertical,
  Lock,
  Plus,
  ShieldCheck,
  Upload,
} from "lucide-react";

const courseConfigs = {
  "web3-fundamentals": {
    title: "Web3 Fundamentals",
    category: "Blockchain",
    level: "Beginner",
    duration: "6 hours",
    description: "Foundational Web3 concepts, wallets, smart contracts, and on-chain interactions.",
    lessons: [
      { title: "Introduction to Blockchain", description: "Core blockchain principles and decentralized systems.", duration: "35 min" },
      { title: "Wallets & Transactions", description: "Wallet setup, custody models, and transaction lifecycle.", duration: "40 min" },
      { title: "Smart Contract Basics", description: "How contracts work and common security patterns.", duration: "45 min" },
    ],
  },
  "defi-strategy-lab": {
    title: "DeFi Strategy Lab",
    category: "DeFi",
    level: "Intermediate",
    duration: "5 hours",
    description: "Practical DeFi strategy frameworks: liquidity, yields, risks, and portfolio controls.",
    lessons: [
      { title: "DeFi Market Structure", description: "AMMs, lending pools, and protocol architecture.", duration: "40 min" },
      { title: "Yield Strategy Design", description: "Constructing and stress-testing DeFi yield strategies.", duration: "50 min" },
      { title: "Risk Controls in DeFi", description: "Impermanent loss, smart contract risk, and drawdown controls.", duration: "45 min" },
    ],
  },
  "rwa-tokenization": {
    title: "RWA and Tokenization",
    category: "Blockchain",
    level: "Advanced",
    duration: "4 hours",
    description: "Tokenizing real-world assets with legal, technical, and liquidity considerations.",
    lessons: [
      { title: "RWA Fundamentals", description: "What qualifies as RWA and why tokenization matters.", duration: "35 min" },
      { title: "Token Design & Compliance", description: "Structuring compliant tokenized assets.", duration: "45 min" },
      { title: "Secondary Liquidity Models", description: "Market access, liquidity routes, and settlement layers.", duration: "40 min" },
    ],
  },
  "web-development": {
    title: "Web Development",
    category: "AI",
    level: "Beginner",
    duration: "8 hours",
    description: "Modern web development fundamentals for building production-ready interfaces.",
    lessons: [
      { title: "HTML/CSS Architecture", description: "Structuring responsive interfaces with clean patterns.", duration: "45 min" },
      { title: "JavaScript Essentials", description: "Core programming concepts and state management basics.", duration: "55 min" },
      { title: "React Fundamentals", description: "Component-driven UI and app structure.", duration: "60 min" },
    ],
  },
  "advanced-smart-contracts": {
    title: "Advanced Smart Contracts",
    category: "Blockchain",
    level: "Advanced",
    duration: "6.5 hours",
    description: "Advanced contract architecture, upgrades, gas optimization, and security testing.",
    lessons: [
      { title: "Contract Architecture Patterns", description: "Modular patterns and proxy-based upgrade flows.", duration: "55 min" },
      { title: "Gas Optimization", description: "Storage, calldata, and execution-cost minimization.", duration: "45 min" },
      { title: "Security Auditing Workflow", description: "Threat modeling, test coverage, and audit checkpoints.", duration: "50 min" },
    ],
  },
  "data-science-foundations": {
    title: "Data Science Foundations",
    category: "AI",
    level: "Intermediate",
    duration: "6 hours",
    description: "Data analysis, model fundamentals, and practical data workflows for decision systems.",
    lessons: [
      { title: "Data Preparation", description: "Data cleaning, transformation, and feature preparation.", duration: "45 min" },
      { title: "Statistical Basics", description: "Inference, distributions, and practical metrics.", duration: "45 min" },
      { title: "Model Fundamentals", description: "Core supervised learning workflows and evaluation.", duration: "50 min" },
    ],
  },
  "crypto-trading-strategy": {
    title: "Crypto Trading Strategy",
    category: "DeFi",
    level: "Intermediate",
    duration: "5.5 hours",
    description: "Risk-aware crypto trading frameworks with execution discipline and analytics.",
    lessons: [
      { title: "Market Structure & Liquidity", description: "Order books, spread dynamics, and liquidity behavior.", duration: "40 min" },
      { title: "Strategy Construction", description: "Trend, mean reversion, and multi-timeframe setups.", duration: "50 min" },
      { title: "Risk and Journal Process", description: "Sizing models, stop logic, and trade review loops.", duration: "45 min" },
    ],
  },
};

const levelOptions = ["Beginner", "Intermediate", "Advanced"];

function buildLessons(lessons = []) {
  return lessons.map((lesson, index) => ({
    id: `${Date.now()}-${index}`,
    title: lesson.title,
    description: lesson.description,
    duration: lesson.duration,
    resource: "",
    quiz: {
      passingScore: 70,
      requireUnlock: true,
      questions: [
        {
          id: `${Date.now()}-q-${index}`,
          prompt: "",
          options: ["", "", "", ""],
          correct: 0,
        },
      ],
    },
    expanded: index === 0,
  }));
}

function CourseUpload({ onBack, courseKey = "web3-fundamentals" }) {
  const config = courseConfigs[courseKey] || courseConfigs["web3-fundamentals"];

  const [courseTitle, setCourseTitle] = useState(config.title);
  const [category, setCategory] = useState(config.category);
  const [skillLevel, setSkillLevel] = useState(config.level);
  const [courseDescription, setCourseDescription] = useState(config.description);
  const [duration, setDuration] = useState(config.duration);
  const [nftCertificate, setNftCertificate] = useState(true);
  const [lessons, setLessons] = useState(() => buildLessons(config.lessons));

  const categories = useMemo(() => ["Blockchain", "AI", "DeFi", "AgriTech", "Biotech", "Financial Literacy"], []);

  const addLesson = () => {
    setLessons((prev) => [
      ...prev,
      {
        id: `${Date.now()}-${prev.length + 1}`,
        title: `Lesson ${prev.length + 1}`,
        description: "",
        duration: "30 min",
        resource: "",
        quiz: {
          passingScore: 70,
          requireUnlock: true,
          questions: [{ id: `${Date.now()}-q-new`, prompt: "", options: ["", "", "", ""], correct: 0 }],
        },
        expanded: true,
      },
    ]);
  };

  const toggleLesson = (id) => {
    setLessons((prev) => prev.map((lesson) => (lesson.id === id ? { ...lesson, expanded: !lesson.expanded } : lesson)));
  };

  const updateLesson = (id, field, value) => {
    setLessons((prev) => prev.map((lesson) => (lesson.id === id ? { ...lesson, [field]: value } : lesson)));
  };

  const updateQuiz = (lessonId, field, value) => {
    setLessons((prev) =>
      prev.map((lesson) =>
        lesson.id === lessonId
          ? {
              ...lesson,
              quiz: { ...lesson.quiz, [field]: value },
            }
          : lesson
      )
    );
  };

  const addQuestion = (lessonId) => {
    setLessons((prev) =>
      prev.map((lesson) =>
        lesson.id === lessonId
          ? {
              ...lesson,
              quiz: {
                ...lesson.quiz,
                questions: [
                  ...lesson.quiz.questions,
                  { id: `${Date.now()}-q`, prompt: "", options: ["", "", "", ""], correct: 0 },
                ],
              },
            }
          : lesson
      )
    );
  };

  const updateQuestion = (lessonId, questionId, field, value) => {
    setLessons((prev) =>
      prev.map((lesson) => {
        if (lesson.id !== lessonId) return lesson;
        return {
          ...lesson,
          quiz: {
            ...lesson.quiz,
            questions: lesson.quiz.questions.map((q) => (q.id === questionId ? { ...q, [field]: value } : q)),
          },
        };
      })
    );
  };

  const updateQuestionOption = (lessonId, questionId, optionIndex, value) => {
    setLessons((prev) =>
      prev.map((lesson) => {
        if (lesson.id !== lessonId) return lesson;
        return {
          ...lesson,
          quiz: {
            ...lesson.quiz,
            questions: lesson.quiz.questions.map((q) => {
              if (q.id !== questionId) return q;
              const nextOptions = [...q.options];
              nextOptions[optionIndex] = value;
              return { ...q, options: nextOptions };
            }),
          },
        };
      })
    );
  };

  return (
    <main className="min-h-screen bg-[#0F1115] text-white">
      <div className="mx-auto w-full max-w-7xl px-4 pb-16 pt-7 sm:px-6 lg:px-8">
        <div className="flex flex-wrap items-center justify-between gap-3">
          {onBack ? (
            <button
              type="button"
              onClick={onBack}
              className="inline-flex items-center gap-2 rounded-xl border border-[#D4AF37]/30 bg-[#111827] px-4 py-2 text-sm font-semibold text-[#F4D03F] transition duration-300 hover:border-[#D4AF37]/60 hover:shadow-lg hover:shadow-[#D4AF37]/20"
            >
              <ArrowLeft className="h-4 w-4" />
              Back to EdTech
            </button>
          ) : null}
          <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
            <button
              type="button"
              className="rounded-xl border border-[#D4AF37]/35 bg-transparent px-5 py-3 text-sm font-semibold text-[#F4D03F] transition duration-300 hover:border-[#D4AF37]/70 hover:bg-[#D4AF37]/10"
            >
              Save Draft
            </button>
            <button
              type="button"
              className="rounded-xl bg-gradient-to-r from-[#C9A227] via-[#D4AF37] to-[#F4D03F] px-5 py-3 text-sm font-semibold text-[#15100A] shadow-lg transition duration-300 hover:brightness-105 hover:shadow-[#D4AF37]/40"
            >
              Publish Course
            </button>
          </div>
        </div>

        <header className="mt-6 rounded-3xl border border-[#D4AF37]/20 bg-gradient-to-br from-[#111827] via-[#0F1115] to-[#151922] px-6 py-10 sm:px-8">
          <h1 className="text-3xl font-semibold text-white sm:text-4xl">Upload New Course</h1>
          <p className="mt-3 max-w-2xl text-sm text-[#9CA3AF] sm:text-base">
            Structure lessons, quizzes, and gated progression for Web3 education.
          </p>
          <p className="mt-4 text-sm font-semibold text-[#D4AF37]">Editing: {config.title}</p>
        </header>

        <section className="mt-8 rounded-2xl border border-[#D4AF37]/20 bg-[#1A1D24] p-5 shadow-lg sm:p-6">
          <h2 className="text-xl font-semibold text-white">Course Information</h2>
          <div className="mt-5 grid gap-4 sm:grid-cols-2">
            <Field label="Course Title">
              <input
                value={courseTitle}
                onChange={(e) => setCourseTitle(e.target.value)}
                className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
              />
            </Field>
            <Field label="Category">
              <select
                value={category}
                onChange={(e) => setCategory(e.target.value)}
                className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
              >
                {categories.map((item) => (
                  <option key={item} value={item} className="bg-[#111827] text-white">
                    {item}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Skill Level">
              <select
                value={skillLevel}
                onChange={(e) => setSkillLevel(e.target.value)}
                className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
              >
                {levelOptions.map((level) => (
                  <option key={level} value={level} className="bg-[#111827] text-white">
                    {level}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Estimated Duration">
              <input
                value={duration}
                onChange={(e) => setDuration(e.target.value)}
                className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
              />
            </Field>
          </div>

          <Field label="Course Description" className="mt-4">
            <textarea
              rows={4}
              value={courseDescription}
              onChange={(e) => setCourseDescription(e.target.value)}
              className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
            />
          </Field>

          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            <Field label="Thumbnail Upload">
              <div className="rounded-lg border border-dashed border-[#D4AF37]/35 bg-[#111827] px-4 py-5 text-sm text-[#9CA3AF]">
                Upload course thumbnail (UI placeholder)
              </div>
            </Field>
            <Field label="NFT Certificate">
              <div className="flex h-full items-center rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3">
                <button
                  type="button"
                  onClick={() => setNftCertificate((prev) => !prev)}
                  className={`relative inline-flex h-7 w-14 items-center rounded-full border transition duration-300 ${
                    nftCertificate ? "border-[#D4AF37]/50 bg-[#D4AF37]/25" : "border-neutral-700 bg-neutral-800"
                  }`}
                >
                  <span
                    className={`inline-block h-5 w-5 transform rounded-full bg-gradient-to-r from-[#C9A227] to-[#F4D03F] transition ${
                      nftCertificate ? "translate-x-8" : "translate-x-1"
                    }`}
                  />
                </button>
                <span className="ml-3 text-sm text-[#9CA3AF]">{nftCertificate ? "Enabled" : "Disabled"}</span>
              </div>
            </Field>
          </div>
        </section>

        <section className="mt-8 grid gap-6 lg:grid-cols-[1.55fr_0.85fr]">
          <div>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
              <h2 className="text-2xl font-semibold text-white">Course Lessons</h2>
              <button
                type="button"
                onClick={addLesson}
                className="inline-flex items-center gap-2 rounded-xl border border-[#D4AF37]/35 bg-transparent px-4 py-2 text-sm font-semibold text-[#F4D03F] transition duration-300 hover:border-[#D4AF37]/70 hover:bg-[#D4AF37]/10"
              >
                <Plus className="h-4 w-4" />
                Add New Lesson
              </button>
            </div>

            <div className="space-y-4">
              {lessons.map((lesson, index) => (
                <article
                  key={lesson.id}
                  className="rounded-xl border border-[#D4AF37]/20 bg-[#1A1D24] p-4 shadow-lg transition duration-300 hover:-translate-y-0.5 hover:border-[#D4AF37]/45"
                >
                  <div className="flex items-start gap-3">
                    <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gradient-to-r from-[#C9A227] to-[#F4D03F] text-sm font-bold text-[#16110A]">
                      {String(index + 1).padStart(2, "0")}
                    </span>

                    <div className="flex-1">
                      <div className="flex items-center justify-between gap-2">
                        <p className="text-sm font-semibold text-white">Lesson {index + 1}</p>
                        <div className="flex items-center gap-2">
                          <button type="button" className="cursor-grab rounded-lg border border-[#D4AF37]/20 p-2 text-[#D4AF37]">
                            <GripVertical className="h-4 w-4" />
                          </button>
                          <button
                            type="button"
                            onClick={() => toggleLesson(lesson.id)}
                            className="rounded-lg border border-[#D4AF37]/20 p-2 text-[#D4AF37]"
                          >
                            {lesson.expanded ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                          </button>
                        </div>
                      </div>

                      {lesson.expanded ? (
                        <div className="mt-4 space-y-3">
                          <Field label="Lesson Title">
                            <input
                              value={lesson.title}
                              onChange={(e) => updateLesson(lesson.id, "title", e.target.value)}
                              className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
                            />
                          </Field>
                          <Field label="Lesson Description">
                            <textarea
                              rows={3}
                              value={lesson.description}
                              onChange={(e) => updateLesson(lesson.id, "description", e.target.value)}
                              className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
                            />
                          </Field>
                          <div className="grid gap-3 sm:grid-cols-2">
                            <Field label="Video Upload">
                              <div className="rounded-lg border border-dashed border-[#D4AF37]/35 bg-[#111827] px-4 py-4 text-sm text-[#9CA3AF]">
                                Upload lesson video (UI placeholder)
                              </div>
                            </Field>
                            <Field label="Resource Upload (PDF, link)">
                              <input
                                value={lesson.resource}
                                onChange={(e) => updateLesson(lesson.id, "resource", e.target.value)}
                                placeholder="https://resource-link"
                                className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 placeholder:text-[#9CA3AF]/80 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
                              />
                            </Field>
                          </div>
                          <Field label="Estimated Duration">
                            <input
                              value={lesson.duration}
                              onChange={(e) => updateLesson(lesson.id, "duration", e.target.value)}
                              className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
                            />
                          </Field>

                          <div className="rounded-xl border border-[#D4AF37]/20 border-l-2 border-l-[#D4AF37]/70 bg-[#111827]/65 p-4">
                            <div className="mb-3 flex items-center justify-between gap-3">
                              <h3 className="text-sm font-semibold text-white">Lesson Quiz (Required to Unlock Next Lesson)</h3>
                              <button
                                type="button"
                                onClick={() => addQuestion(lesson.id)}
                                className="inline-flex items-center gap-1 rounded-lg border border-[#D4AF37]/35 px-3 py-1.5 text-xs font-semibold text-[#F4D03F] transition duration-300 hover:bg-[#D4AF37]/10"
                              >
                                <Plus className="h-3.5 w-3.5" />
                                Add Question
                              </button>
                            </div>

                            <div className="space-y-3">
                              {lesson.quiz.questions.map((question, qIndex) => (
                                <div key={question.id} className="rounded-lg border border-[#D4AF37]/15 bg-[#0F1115] p-3">
                                  <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-[#D4AF37]/90">
                                    Question {qIndex + 1}
                                  </p>
                                  <input
                                    value={question.prompt}
                                    onChange={(e) => updateQuestion(lesson.id, question.id, "prompt", e.target.value)}
                                    placeholder="Enter quiz question"
                                    className="w-full rounded-lg border border-[#D4AF37]/20 bg-[#111827] px-3 py-2 text-sm text-white outline-none transition duration-300 placeholder:text-[#9CA3AF]/80 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
                                  />
                                  <div className="mt-2 grid gap-2 sm:grid-cols-2">
                                    {question.options.map((option, optionIndex) => {
                                      const optionLabel = String.fromCharCode(65 + optionIndex);
                                      const isCorrect = question.correct === optionIndex;
                                      return (
                                        <div key={`${question.id}-${optionLabel}`} className="rounded-md border border-[#D4AF37]/15 bg-[#111827] p-2">
                                          <div className="mb-1 flex items-center justify-between">
                                            <span className="text-xs font-semibold text-[#D4AF37]">Option {optionLabel}</span>
                                            <label className="inline-flex items-center gap-1 text-[11px] text-[#9CA3AF]">
                                              <input
                                                type="radio"
                                                name={`correct-${lesson.id}-${question.id}`}
                                                checked={isCorrect}
                                                onChange={() => updateQuestion(lesson.id, question.id, "correct", optionIndex)}
                                                className="h-3.5 w-3.5 border-[#D4AF37]/40 bg-[#111827] text-[#D4AF37] focus:ring-[#D4AF37]/30"
                                              />
                                              Correct
                                            </label>
                                          </div>
                                          <input
                                            value={option}
                                            onChange={(e) => updateQuestionOption(lesson.id, question.id, optionIndex, e.target.value)}
                                            placeholder={`Choice ${optionLabel}`}
                                            className="w-full rounded-md border border-[#D4AF37]/20 bg-[#0F1115] px-2 py-2 text-xs text-white outline-none transition duration-300 placeholder:text-[#9CA3AF]/80 focus:border-[#D4AF37]/60 focus:ring-1 focus:ring-[#D4AF37]/25"
                                          />
                                        </div>
                                      );
                                    })}
                                  </div>
                                </div>
                              ))}
                            </div>

                            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                              <Field label="Passing Score (%)">
                                <input
                                  type="number"
                                  value={lesson.quiz.passingScore}
                                  onChange={(e) => updateQuiz(lesson.id, "passingScore", Number(e.target.value || 0))}
                                  className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-2.5 text-sm text-white outline-none transition duration-300 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
                                />
                              </Field>
                              <Field label="Unlock Rule">
                                <label className="flex h-full items-center gap-2 rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-3 py-2.5 text-xs text-[#9CA3AF]">
                                  <input
                                    type="checkbox"
                                    checked={lesson.quiz.requireUnlock}
                                    onChange={(e) => updateQuiz(lesson.id, "requireUnlock", e.target.checked)}
                                    className="h-4 w-4 rounded border-[#D4AF37]/40 bg-[#111827] text-[#D4AF37] focus:ring-[#D4AF37]/30"
                                  />
                                  Require quiz completion before next lesson unlocks
                                </label>
                              </Field>
                            </div>
                            <p className="mt-1 text-xs text-[#9CA3AF]">
                              Students must pass this quiz before proceeding to the next lesson.
                            </p>
                          </div>
                        </div>
                      ) : null}
                    </div>
                  </div>
                </article>
              ))}
            </div>
          </div>

          <aside className="space-y-4">
            <div className="rounded-xl border border-[#D4AF37]/20 bg-[#1A1D24] p-4 shadow-lg">
              <h3 className="text-base font-semibold text-white">Lesson Flow Preview</h3>
              <div className="mt-4 space-y-3">
                {lessons.map((lesson, index) => (
                  <div key={`preview-${lesson.id}`} className="rounded-lg border border-[#D4AF37]/15 bg-[#111827] p-3">
                    <div className="flex items-center justify-between">
                      <p className="text-sm text-white">{lesson.title || `Lesson ${index + 1}`}</p>
                      {index === 0 ? (
                        <CheckCircle2 className="h-4 w-4 text-[#D4AF37]" />
                      ) : (
                        <Lock className="h-4 w-4 text-[#9CA3AF]" />
                      )}
                    </div>
                    <p className="mt-1 text-xs text-[#9CA3AF]">{index === 0 ? "Quiz required to unlock next lesson" : "Locked until previous quiz passed"}</p>
                  </div>
                ))}
                <div className="rounded-lg border border-[#D4AF37]/15 bg-[#111827] p-3 text-sm font-semibold text-[#D4AF37]">
                  Completed
                </div>
              </div>
            </div>

            <div className="rounded-xl border border-[#D4AF37]/20 bg-[#1A1D24] p-4 shadow-lg">
              <h3 className="text-base font-semibold text-white">Course Completion Requirements</h3>
              <div className="mt-3 divide-y divide-[#D4AF37]/15 text-sm text-[#9CA3AF]">
                <p className="py-2">All lessons completed</p>
                <p className="py-2">All quizzes passed</p>
                <p className="py-2">Minimum passing average achieved</p>
              </div>
            </div>
          </aside>
        </section>

        <section className="mt-10 rounded-2xl border border-[#D4AF37]/20 bg-[#1A1D24] p-6 text-center shadow-lg">
          <button
            type="button"
            className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#C9A227] via-[#D4AF37] to-[#F4D03F] px-6 py-4 text-base font-semibold text-[#15100A] shadow-lg transition duration-300 hover:brightness-105 hover:shadow-[#D4AF37]/40 sm:w-auto sm:min-w-[280px]"
          >
            <Upload className="h-4 w-4" />
            Publish Course
          </button>
          <p className="mx-auto mt-3 max-w-3xl text-xs text-[#9CA3AF]">
            Once published, students will progress lesson-by-lesson and must pass each quiz before unlocking the next module.
          </p>
          <p className="mt-3 inline-flex items-center gap-2 rounded-md border border-[#D4AF37]/25 bg-[#111827] px-3 py-1.5 text-[11px] text-[#D4AF37]">
            <ShieldCheck className="h-3.5 w-3.5" />
            Structured, gated progression enabled for blockchain-grade learning integrity.
          </p>
        </section>
      </div>
    </main>
  );
}

function Field({ label, className = "", children }) {
  return (
    <label className={`block ${className}`}>
      <span className="mb-2 block text-sm font-medium text-white">{label}</span>
      {children}
    </label>
  );
}

export default CourseUpload;
