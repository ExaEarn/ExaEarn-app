import { useMemo, useState } from "react";
import {
  ArrowLeft,
  Award,
  BarChart3,
  BellRing,
  BookOpen,
  CheckCircle2,
  ChevronRight,
  FileDown,
  FilePlus2,
  FolderPlus,
  GraduationCap,
  Layers,
  Lock,
  MessageSquareMore,
  Pencil,
  Search,
  Send,
  ShieldCheck,
  Sparkles,
  Unlock,
  Upload,
  Users,
  Video,
} from "lucide-react";

const overviewStats = [
  { label: "Total Courses Published", value: "12", icon: BookOpen },
  { label: "Active Cohorts", value: "4", icon: Layers },
  { label: "Total Enrolled Students", value: "1,280", icon: Users },
  { label: "Course Completion Rate", value: "78%", icon: CheckCircle2 },
];

const courseSeed = [
  {
    id: "course-1",
    title: "Web3 Fundamentals",
    status: "Live",
    lessons: 18,
    students: 420,
    completion: 82,
  },
  {
    id: "course-2",
    title: "DeFi Strategy Lab",
    status: "Live",
    lessons: 14,
    students: 336,
    completion: 74,
  },
  {
    id: "course-3",
    title: "RWA & Tokenization",
    status: "Draft",
    lessons: 10,
    students: 98,
    completion: 51,
  },
];

const cohortSeed = [
  { id: "cohort-a", title: "Cohort A - January 2026", students: 220, active: 184, quizAverage: "84%", progress: 77 },
  { id: "cohort-b", title: "Cohort B - March 2026", students: 160, active: 126, quizAverage: "79%", progress: 63 },
];

const toolCards = [
  { id: "tool-1", title: "Send Broadcast Message", icon: MessageSquareMore },
  { id: "tool-2", title: "Upload Bonus Content", icon: Upload },
  { id: "tool-3", title: "Issue Completion Certificates", icon: Award },
  { id: "tool-4", title: "Download Performance Report", icon: FileDown },
];

function InstructorWorkshop({ onBack }) {
  const [search, setSearch] = useState("");
  const [selectedCourseId, setSelectedCourseId] = useState(courseSeed[0].id);
  const [selectedLessonId, setSelectedLessonId] = useState("lesson-01");
  const [requireQuizPass, setRequireQuizPass] = useState(true);
  const [passingScore, setPassingScore] = useState("70");

  const lessons = useMemo(
    () => [
      { id: "lesson-01", title: "Lesson 01", locked: false, quizAttached: true },
      { id: "lesson-02", title: "Lesson 02", locked: true, quizAttached: true },
      { id: "lesson-03", title: "Lesson 03", locked: true, quizAttached: false },
    ],
    []
  );

  const filteredCourses = useMemo(() => {
    const term = search.trim().toLowerCase();
    if (!term) return courseSeed;
    return courseSeed.filter((course) => course.title.toLowerCase().includes(term));
  }, [search]);

  const activeCourse = courseSeed.find((course) => course.id === selectedCourseId) || courseSeed[0];

  return (
    <main className="min-h-screen bg-[var(--exa-bg-primary)] text-[var(--exa-text-primary)]">
      <header className="sticky top-0 z-30 border-b border-[var(--exa-border-active)] bg-[var(--exa-bg-primary)] backdrop-blur">
        <div className="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
          <div>
            <h1 className="text-2xl font-semibold text-[var(--exa-text-primary)]">Instructor Workshop</h1>
            <p className="text-sm text-[var(--exa-text-muted)]">Manage lessons, cohorts and course performance</p>
          </div>

          <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
            <button
              type="button"
              className="rounded-xl bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-4 py-2.5 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-lg transition duration-300 hover:brightness-105 hover:shadow-[var(--exa-shadow-gold)]"
            >
              + New Course
            </button>
            <button
              type="button"
              className="rounded-xl border border-[var(--exa-border-active)] bg-transparent px-4 py-2.5 text-sm font-semibold text-[var(--exa-gold-light)] transition duration-300 hover:border-[var(--exa-border-active)] hover:bg-[var(--exa-gold-surface)]"
            >
              Publish Updates
            </button>
            <div className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface)] text-xs font-semibold text-[var(--exa-gold)]">
              IW
            </div>
          </div>
        </div>
      </header>

      <div className="mx-auto w-full max-w-7xl px-4 pb-16 pt-6 sm:px-6 lg:px-8">
        {onBack ? (
          <button
            type="button"
            onClick={onBack}
            className="mb-5 inline-flex items-center gap-2 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-2 text-sm font-semibold text-[var(--exa-gold-light)] transition duration-300 hover:border-[var(--exa-border-active)] hover:shadow-lg hover:shadow-[var(--exa-shadow-gold)]"
          >
            <ArrowLeft className="h-4 w-4" />
            Back to EdTech
          </button>
        ) : null}

        <section className="grid gap-4 sm:grid-cols-2">
          {overviewStats.map((stat) => {
            const Icon = stat.icon;
            return (
              <article
                key={stat.label}
                className="rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] p-5 shadow-lg transition duration-300 hover:-translate-y-1 hover:border-[var(--exa-border-active)]"
              >
                <div className="flex items-start justify-between">
                  <div>
                    <p className="text-sm text-[var(--exa-text-muted)]">{stat.label}</p>
                    <p className="mt-2 text-2xl font-semibold text-[var(--exa-gold)]">{stat.value}</p>
                  </div>
                  <span className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[var(--exa-border)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold)]">
                    <Icon className="h-5 w-5" />
                  </span>
                </div>
              </article>
            );
          })}
        </section>

        <section className="mt-8">
          <div className="mb-4 flex items-center justify-between gap-3">
            <h2 className="text-xl font-semibold text-[var(--exa-text-primary)]">Your Courses</h2>
          </div>

          <label className="relative mb-4 block">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--exa-text-muted)]" />
            <input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search course title"
              className="w-full rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] py-3 pl-10 pr-4 text-sm text-[var(--exa-text-primary)] outline-none transition duration-300 placeholder:text-[var(--exa-text-muted)] focus:border-[var(--exa-border-active)] focus:ring-2 focus:ring-[var(--exa-gold)]"
            />
          </label>

          <div className="grid gap-4">
            {filteredCourses.map((course) => (
              <article
                key={course.id}
                className="rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] p-4 shadow-lg transition duration-300 hover:border-[var(--exa-border-active)]"
              >
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                  <div className="flex min-w-0 items-center gap-3">
                    <div className="h-14 w-14 rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)]" />
                    <div className="min-w-0">
                      <p className="truncate text-base font-semibold text-[var(--exa-text-primary)]">{course.title}</p>
                      <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-[var(--exa-text-muted)]">
                        <span className={`rounded-full border px-2 py-0.5 ${course.status === "Live" ? "border-[var(--exa-border-active)] text-[var(--exa-gold)]" : "border-neutral-600 text-[var(--exa-text-secondary)]"}`}>
                          {course.status}
                        </span>
                        <span>{course.lessons} lessons</span>
                        <span>{course.students} students</span>
                        <span>{course.completion}% completion</span>
                      </div>
                    </div>
                  </div>

                  <div className="flex flex-col gap-2 sm:flex-row">
                    <button
                      type="button"
                      onClick={() => setSelectedCourseId(course.id)}
                      className="rounded-xl border border-[var(--exa-border-active)] bg-transparent px-4 py-2 text-sm font-semibold text-[var(--exa-gold-light)] transition duration-300 hover:border-[var(--exa-border-active)] hover:bg-[var(--exa-gold-surface)]"
                    >
                      Edit
                    </button>
                    <button
                      type="button"
                      className="rounded-xl border border-[var(--exa-border-active)] bg-transparent px-4 py-2 text-sm font-semibold text-[var(--exa-gold-light)] transition duration-300 hover:border-[var(--exa-border-active)] hover:bg-[var(--exa-gold-surface)]"
                    >
                      Manage Cohort
                    </button>
                  </div>
                </div>
              </article>
            ))}
          </div>
        </section>

        <section className="mt-8 grid gap-5 lg:grid-cols-[0.9fr_1.1fr]">
          <div className="rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] p-4 shadow-lg">
            <div className="mb-3 flex items-center justify-between">
              <h3 className="text-lg font-semibold text-[var(--exa-text-primary)]">Lessons - {activeCourse.title}</h3>
              <button
                type="button"
                className="inline-flex items-center gap-2 rounded-xl border border-[var(--exa-border-active)] bg-transparent px-3 py-2 text-xs font-semibold text-[var(--exa-gold-light)] transition duration-300 hover:border-[var(--exa-border-active)] hover:bg-[var(--exa-gold-surface)]"
              >
                <FilePlus2 className="h-3.5 w-3.5" />
                Add Lesson
              </button>
            </div>
            <div className="space-y-2">
              {lessons.map((lesson) => (
                <button
                  key={lesson.id}
                  type="button"
                  onClick={() => setSelectedLessonId(lesson.id)}
                  className={`flex w-full items-center justify-between rounded-lg border px-3 py-3 text-left transition ${
                    selectedLessonId === lesson.id
                      ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)]"
                      : "border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] hover:border-[var(--exa-border-active)]"
                  }`}
                >
                  <span className="flex items-center gap-2">
                    {lesson.locked ? <Lock className="h-4 w-4 text-[var(--exa-text-muted)]" /> : <Unlock className="h-4 w-4 text-[var(--exa-gold)]" />}
                    <span className="text-sm text-[var(--exa-text-primary)]">{lesson.title}</span>
                  </span>
                  <span className="flex items-center gap-2 text-xs text-[var(--exa-text-muted)]">
                    {lesson.quizAttached ? "Quiz attached" : "No quiz"}
                    <Pencil className="h-3.5 w-3.5 text-[var(--exa-gold)]" />
                  </span>
                </button>
              ))}
            </div>
          </div>

          <div className="rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] p-4 shadow-lg">
            <h3 className="text-lg font-semibold text-[var(--exa-text-primary)]">Lesson Editor</h3>
            <div className="mt-4 space-y-3">
              <Field label="Lesson Title">
                <input className="w-full rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-4 py-3 text-sm text-[var(--exa-text-primary)] outline-none transition duration-300 focus:border-[var(--exa-border-active)] focus:ring-2 focus:ring-[var(--exa-gold)]" defaultValue="Introduction to Blockchain" />
              </Field>
              <Field label="Lesson Description">
                <textarea rows={3} className="w-full rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-4 py-3 text-sm text-[var(--exa-text-primary)] outline-none transition duration-300 focus:border-[var(--exa-border-active)] focus:ring-2 focus:ring-[var(--exa-gold)]" defaultValue="Overview of core blockchain architecture, decentralized ledgers and trust mechanisms." />
              </Field>
              <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Video Upload Placeholder">
                  <div className="rounded-lg border border-dashed border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-3 py-4 text-xs text-[var(--exa-text-muted)]">Upload lesson video (UI placeholder)</div>
                </Field>
                <Field label="Resource Upload">
                  <input placeholder="PDF or link" className="w-full rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-4 py-3 text-sm text-[var(--exa-text-primary)] outline-none transition duration-300 placeholder:text-[var(--exa-text-muted)] focus:border-[var(--exa-border-active)] focus:ring-2 focus:ring-[var(--exa-gold)]" />
                </Field>
              </div>
              <Field label="Duration">
                <input defaultValue="35 min" className="w-full rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-4 py-3 text-sm text-[var(--exa-text-primary)] outline-none transition duration-300 focus:border-[var(--exa-border-active)] focus:ring-2 focus:ring-[var(--exa-gold)]" />
              </Field>
            </div>

            <div className="mt-5 border-t border-[var(--exa-border-active)] pt-4">
              <h4 className="text-sm font-semibold text-[var(--exa-text-primary)]">Quiz Configuration</h4>
              <div className="mt-3 space-y-3">
                <button
                  type="button"
                  className="inline-flex items-center gap-2 rounded-lg border border-[var(--exa-border-active)] px-3 py-2 text-xs font-semibold text-[var(--exa-gold-light)] transition duration-300 hover:bg-[var(--exa-gold-surface)]"
                >
                  <FilePlus2 className="h-3.5 w-3.5" />
                  Add Question
                </button>
                <input placeholder="Enter question" className="w-full rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-4 py-3 text-sm text-[var(--exa-text-primary)] outline-none transition duration-300 placeholder:text-[var(--exa-text-muted)] focus:border-[var(--exa-border-active)] focus:ring-2 focus:ring-[var(--exa-gold)]" />
                <div className="grid gap-2 sm:grid-cols-2">
                  <input placeholder="Option A" className="rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-3 py-2 text-xs text-[var(--exa-text-primary)] outline-none focus:border-[var(--exa-border-active)] focus:ring-1 focus:ring-[var(--exa-gold)]" />
                  <input placeholder="Option B" className="rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-3 py-2 text-xs text-[var(--exa-text-primary)] outline-none focus:border-[var(--exa-border-active)] focus:ring-1 focus:ring-[var(--exa-gold)]" />
                  <input placeholder="Option C" className="rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-3 py-2 text-xs text-[var(--exa-text-primary)] outline-none focus:border-[var(--exa-border-active)] focus:ring-1 focus:ring-[var(--exa-gold)]" />
                  <input placeholder="Option D" className="rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-3 py-2 text-xs text-[var(--exa-text-primary)] outline-none focus:border-[var(--exa-border-active)] focus:ring-1 focus:ring-[var(--exa-gold)]" />
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                  <Field label="Passing Score (%)">
                    <input
                      value={passingScore}
                      onChange={(e) => setPassingScore(e.target.value)}
                      className="w-full rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-3 py-2 text-sm text-[var(--exa-text-primary)] outline-none focus:border-[var(--exa-border-active)] focus:ring-1 focus:ring-[var(--exa-gold)]"
                    />
                  </Field>
                  <Field label="Unlock Rule">
                    <label className="flex items-center gap-2 rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-3 py-2 text-xs text-[var(--exa-text-muted)]">
                      <input
                        type="checkbox"
                        checked={requireQuizPass}
                        onChange={(e) => setRequireQuizPass(e.target.checked)}
                        className="h-4 w-4 rounded border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] text-[var(--exa-gold)] focus:ring-[var(--exa-gold)]"
                      />
                      Require quiz pass before next lesson unlock
                    </label>
                  </Field>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="mt-8">
          <h2 className="text-xl font-semibold text-[var(--exa-text-primary)]">Cohort Management</h2>
          <div className="mt-4 grid gap-4 md:grid-cols-2">
            {cohortSeed.map((cohort) => (
              <article key={cohort.id} className="rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] p-4 shadow-lg">
                <h3 className="text-base font-semibold text-[var(--exa-text-primary)]">{cohort.title}</h3>
                <div className="mt-3 grid grid-cols-2 gap-2 text-xs text-[var(--exa-text-muted)]">
                  <p>Total Students: <span className="text-[var(--exa-gold)]">{cohort.students}</span></p>
                  <p>Active Students: <span className="text-[var(--exa-gold)]">{cohort.active}</span></p>
                  <p>Average Quiz Score: <span className="text-[var(--exa-gold)]">{cohort.quizAverage}</span></p>
                  <p>Progress: <span className="text-[var(--exa-gold)]">{cohort.progress}%</span></p>
                </div>
                <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-[var(--exa-surface-elevated)]">
                  <div className="h-full rounded-full bg-gradient-to-r from-[var(--exa-gold-dark)] to-[var(--exa-gold-light)]" style={{ width: `${cohort.progress}%` }} />
                </div>
                <div className="mt-4 flex flex-wrap gap-2">
                  <button type="button" className="rounded-lg border border-[var(--exa-border-active)] px-3 py-2 text-xs font-semibold text-[var(--exa-gold-light)] hover:bg-[var(--exa-gold-surface)]">
                    View Students
                  </button>
                  <button type="button" className="rounded-lg border border-[var(--exa-border-active)] px-3 py-2 text-xs font-semibold text-[var(--exa-gold-light)] hover:bg-[var(--exa-gold-surface)]">
                    Send Announcement
                  </button>
                  <button type="button" className="rounded-lg border border-[var(--exa-border-active)] px-3 py-2 text-xs font-semibold text-[var(--exa-text-secondary)] hover:border-[var(--exa-border-active)]">
                    Close Cohort
                  </button>
                </div>
              </article>
            ))}
          </div>
        </section>

        <section className="mt-8">
          <h2 className="text-xl font-semibold text-[var(--exa-text-primary)]">Instructor Workshop Tools</h2>
          <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {toolCards.map((tool) => {
              const Icon = tool.icon;
              return (
                <button
                  key={tool.id}
                  type="button"
                  className="rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] p-4 text-left shadow-lg transition duration-300 hover:-translate-y-1 hover:border-[var(--exa-border-active)] hover:shadow-[var(--exa-shadow-gold)]"
                >
                  <span className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[var(--exa-border)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold)]">
                    <Icon className="h-4 w-4" />
                  </span>
                  <p className="mt-3 text-sm font-semibold text-[var(--exa-text-primary)]">{tool.title}</p>
                </button>
              );
            })}
          </div>
        </section>

        <section className="mt-8">
          <h2 className="text-xl font-semibold text-[var(--exa-text-primary)]">Performance Analytics</h2>
          <div className="mt-4 rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] p-4 shadow-lg">
            <div className="rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] p-4">
              <div className="mb-3 flex items-center gap-2 text-[var(--exa-gold)]">
                <BarChart3 className="h-4 w-4" />
                <p className="text-sm font-semibold">Engagement & Outcomes (UI Placeholder)</p>
              </div>
              <div className="grid gap-3 text-xs text-[var(--exa-text-muted)] sm:grid-cols-2">
                <div className="rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-bg-primary)] p-3">Lesson Engagement</div>
                <div className="rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-bg-primary)] p-3">Quiz Pass Rate</div>
                <div className="rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-bg-primary)] p-3">Drop-off Points</div>
                <div className="rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-bg-primary)] p-3">Completion Trends</div>
              </div>
            </div>
          </div>
        </section>

        <section className="mt-6 rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] p-4">
          <p className="border-l-2 border-[var(--exa-border-active)] pl-3 text-sm text-[var(--exa-text-muted)]">
            Your content is protected by ExaEarn decentralized infrastructure and encrypted storage.
          </p>
        </section>
      </div>
    </main>
  );
}

function Field({ label, children }) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-[var(--exa-gold)]">{label}</span>
      {children}
    </label>
  );
}

export default InstructorWorkshop;
