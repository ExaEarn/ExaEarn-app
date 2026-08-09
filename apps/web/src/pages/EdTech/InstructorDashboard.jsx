import {
  ArrowLeft,
  Bell,
  BookOpen,
  ChartColumn,
  CheckCircle2,
  Clock3,
  Coins,
  Download,
  FileBadge2,
  FilePlus2,
  GraduationCap,
  MessageSquarePlus,
  Send,
  ShieldCheck,
  Sparkles,
  Users,
} from "lucide-react";

const metrics = [
  { label: "Total Courses", value: "12", icon: BookOpen },
  { label: "Active Students", value: "1,280", icon: Users },
  { label: "Monthly Revenue", value: "8.42 ETH", icon: Coins },
  { label: "Course Completion Rate", value: "78%", icon: CheckCircle2 },
];

const performanceCourses = [
  { id: "c1", title: "Web3 Fundamentals", students: 420, quizScore: "83%", completion: "81%", status: "Live" },
  { id: "c2", title: "DeFi Strategy Lab", students: 336, quizScore: "79%", completion: "74%", status: "Live" },
  { id: "c3", title: "RWA & Tokenization", students: 196, quizScore: "75%", completion: "62%", status: "Draft" },
];

const studentActivity = [
  { name: "Aisha Bello", course: "Web3 Fundamentals", lesson: "Lesson 06", quiz: "88%", status: "Completed" },
  { name: "Daniel Mensah", course: "DeFi Strategy Lab", lesson: "Lesson 04", quiz: "72%", status: "In Progress" },
  { name: "Mariam Yusuf", course: "RWA & Tokenization", lesson: "Lesson 03", quiz: "91%", status: "Completed" },
  { name: "John Okafor", course: "Web Development", lesson: "Lesson 05", quiz: "67%", status: "Needs Review" },
];

const engagementStats = [
  { label: "Lesson Drop-Off Rate", value: "12%" },
  { label: "Average Study Time", value: "46 min" },
  { label: "Quiz Pass Rate", value: "84%" },
  { label: "Student Retention", value: "88%" },
];

const quickActions = [
  { label: "Upload New Lesson", icon: FilePlus2 },
  { label: "Add Quiz", icon: Sparkles },
  { label: "Manage Cohorts", icon: Users },
  { label: "Issue Certificates", icon: FileBadge2 },
  { label: "Download Report", icon: Download },
];

function InstructorDashboard({ onBack }) {
  return (
    <main className="min-h-screen bg-[var(--exa-bg-primary)] text-[var(--exa-text-primary)]">
      <header className="sticky top-0 z-30 border-b border-[var(--exa-border-active)] bg-[var(--exa-bg-primary)] backdrop-blur">
        <div className="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
          <div>
            <h1 className="text-2xl font-semibold text-[var(--exa-text-primary)]">Instructor Dashboard</h1>
            <p className="text-sm text-[var(--exa-text-muted)]">Monitor performance, revenue, and student engagement.</p>
          </div>
          <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
            <button
              type="button"
              className="rounded-xl bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-4 py-2.5 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-lg transition duration-300 hover:brightness-105 hover:shadow-[var(--exa-shadow-gold)]"
            >
              + Create New Course
            </button>
            <button
              type="button"
              className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)] text-[var(--exa-gold)] transition duration-300 hover:border-[var(--exa-border-active)]"
              aria-label="Notifications"
            >
              <Bell className="h-4 w-4" />
            </button>
            <div className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface)] text-xs font-semibold text-[var(--exa-gold)]">
              ID
            </div>
          </div>
        </div>
      </header>

      <div className="mx-auto w-full max-w-7xl px-4 pb-16 pt-6 sm:px-6 lg:px-8">
        {onBack ? (
          <button
            type="button"
            onClick={onBack}
            className="mb-5 inline-flex items-center gap-2 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-2 text-sm font-semibold text-[var(--exa-gold-light)] transition duration-300 hover:border-[var(--exa-border-active)]"
          >
            <ArrowLeft className="h-4 w-4" />
            Back to EdTech
          </button>
        ) : null}

        <section className="grid gap-4 sm:grid-cols-2">
          {metrics.map((item) => {
            const Icon = item.icon;
            return (
              <article
                key={item.label}
                className="rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] p-5 shadow-lg transition duration-300 hover:-translate-y-1 hover:border-[var(--exa-border-active)]"
              >
                <div className="flex items-start justify-between">
                  <div>
                    <p className="text-sm text-[var(--exa-text-muted)]">{item.label}</p>
                    <p className="mt-2 text-2xl font-semibold text-[var(--exa-gold)]">{item.value}</p>
                  </div>
                  <span className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[var(--exa-border)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold)]">
                    <Icon className="h-5 w-5" />
                  </span>
                </div>
              </article>
            );
          })}
        </section>

        <section className="mt-8 rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] p-5 shadow-lg">
          <h2 className="text-xl font-semibold text-[var(--exa-text-primary)]">Revenue Analytics</h2>
          <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <StatItem label="Total Earnings" value="42.80 ETH" />
            <StatItem label="Pending Payout" value="1.24 ETH" />
            <StatItem label="NFT Certificate Revenue" value="6.12 ETH" />
            <StatItem label="Referral Revenue" value="2.08 ETH" />
          </div>
          <div className="mt-5 rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] p-4">
            <p className="mb-3 text-xs uppercase tracking-wide text-[var(--exa-gold)]">Revenue Trend (UI Placeholder)</p>
            <div className="h-28 rounded-md bg-gradient-to-r from-[var(--exa-surface)] via-[var(--exa-gold-surface)] to-[var(--exa-surface)]" />
          </div>
        </section>

        <section className="mt-8">
          <h2 className="text-xl font-semibold text-[var(--exa-text-primary)]">Course Performance</h2>
          <div className="mt-4 grid gap-4">
            {performanceCourses.map((course) => (
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
                        <span>{course.students} students</span>
                        <span>{course.quizScore} avg quiz</span>
                        <span>{course.completion} completion</span>
                        <span className={`rounded-full border px-2 py-0.5 ${course.status === "Live" ? "border-[var(--exa-border-active)] text-[var(--exa-gold)]" : "border-neutral-600 text-[var(--exa-text-secondary)]"}`}>
                          {course.status}
                        </span>
                      </div>
                    </div>
                  </div>
                  <div className="flex flex-col gap-2 sm:flex-row">
                    <button
                      type="button"
                      className="rounded-xl border border-[var(--exa-border-active)] bg-transparent px-4 py-2 text-sm font-semibold text-[var(--exa-gold-light)] transition duration-300 hover:bg-[var(--exa-gold-surface)]"
                    >
                      View Analytics
                    </button>
                    <button
                      type="button"
                      className="rounded-xl border border-[var(--exa-border-active)] bg-transparent px-4 py-2 text-sm font-semibold text-[var(--exa-gold-light)] transition duration-300 hover:bg-[var(--exa-gold-surface)]"
                    >
                      Manage Course
                    </button>
                  </div>
                </div>
              </article>
            ))}
          </div>
        </section>

        <section className="mt-8 rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] p-5 shadow-lg">
          <h2 className="text-xl font-semibold text-[var(--exa-text-primary)]">Recent Student Activity</h2>
          <div className="mt-4 overflow-x-auto">
            <table className="w-full min-w-[720px] text-left text-sm">
              <thead>
                <tr className="border-b border-[var(--exa-border-active)] text-[var(--exa-gold)]">
                  <th className="pb-3 font-semibold">Student Name</th>
                  <th className="pb-3 font-semibold">Course</th>
                  <th className="pb-3 font-semibold">Lesson Completed</th>
                  <th className="pb-3 font-semibold">Quiz Score</th>
                  <th className="pb-3 font-semibold">Status</th>
                </tr>
              </thead>
              <tbody>
                {studentActivity.map((row) => (
                  <tr key={`${row.name}-${row.course}`} className="border-b border-[var(--exa-border-active)] text-[var(--exa-text-muted)]">
                    <td className="py-3">{row.name}</td>
                    <td className="py-3">{row.course}</td>
                    <td className="py-3">{row.lesson}</td>
                    <td className="py-3 text-[var(--exa-gold)]">{row.quiz}</td>
                    <td className="py-3">{row.status}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        <section className="mt-8 grid gap-5 lg:grid-cols-2">
          <div className="rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] p-5 shadow-lg">
            <h2 className="text-xl font-semibold text-[var(--exa-text-primary)]">Instructor Announcements</h2>
            <div className="mt-4 space-y-3">
              <label className="block">
                <span className="mb-2 block text-xs text-[var(--exa-text-muted)]">Message</span>
                <textarea
                  rows={4}
                  placeholder="Write announcement to students..."
                  className="w-full rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-4 py-3 text-sm text-[var(--exa-text-primary)] outline-none transition duration-300 placeholder:text-[var(--exa-text-muted)] focus:border-[var(--exa-border-active)] focus:ring-2 focus:ring-[var(--exa-gold)]"
                />
              </label>
              <label className="block">
                <span className="mb-2 block text-xs text-[var(--exa-text-muted)]">Select Course</span>
                <select className="w-full rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-4 py-3 text-sm text-[var(--exa-text-primary)] outline-none transition duration-300 focus:border-[var(--exa-border-active)] focus:ring-2 focus:ring-[var(--exa-gold)]">
                  {performanceCourses.map((course) => (
                    <option key={course.id} value={course.id} className="bg-[var(--exa-surface-elevated)] text-[var(--exa-text-primary)]">
                      {course.title}
                    </option>
                  ))}
                </select>
              </label>
              <label className="flex items-center gap-2 rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-3 py-2.5 text-sm text-[var(--exa-text-muted)]">
                <input type="checkbox" className="h-4 w-4 rounded border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] text-[var(--exa-gold)] focus:ring-[var(--exa-gold)]" />
                Send to cohort
              </label>
              <button
                type="button"
                className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-5 py-3 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-lg transition duration-300 hover:brightness-105 hover:shadow-[var(--exa-shadow-gold)]"
              >
                <Send className="h-4 w-4" />
                Send Announcement
              </button>
            </div>
          </div>

          <div className="space-y-5">
            <div className="rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] p-5 shadow-lg">
              <h2 className="text-xl font-semibold text-[var(--exa-text-primary)]">Engagement Analytics</h2>
              <div className="mt-4 grid gap-3 sm:grid-cols-2">
                {engagementStats.map((item) => (
                  <article key={item.label} className="rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] p-3">
                    <p className="text-xs text-[var(--exa-text-muted)]">{item.label}</p>
                    <p className="mt-2 text-lg font-semibold text-[var(--exa-gold)]">{item.value}</p>
                  </article>
                ))}
              </div>
            </div>

            <div className="rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] p-5 shadow-lg">
              <h2 className="text-xl font-semibold text-[var(--exa-text-primary)]">Quick Actions</h2>
              <div className="mt-4 grid gap-3 sm:grid-cols-2">
                {quickActions.map((action) => {
                  const Icon = action.icon;
                  return (
                    <button
                      key={action.label}
                      type="button"
                      className="rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] p-3 text-left transition duration-300 hover:-translate-y-0.5 hover:border-[var(--exa-border-active)]"
                    >
                      <span className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-[var(--exa-border)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold)]">
                        <Icon className="h-4 w-4" />
                      </span>
                      <p className="mt-2 text-sm font-semibold text-[var(--exa-text-primary)]">{action.label}</p>
                    </button>
                  );
                })}
              </div>
            </div>
          </div>
        </section>

        <section className="mt-6 rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] p-4">
          <p className="border-l-2 border-[var(--exa-border-active)] pl-3 text-sm text-[var(--exa-text-muted)]">
            Your instructor dashboard is powered by ExaEarn decentralized infrastructure and encrypted blockchain-backed tracking.
          </p>
        </section>
      </div>
    </main>
  );
}

function StatItem({ label, value }) {
  return (
    <div className="rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] p-3">
      <p className="text-xs text-[var(--exa-text-muted)]">{label}</p>
      <p className="mt-2 text-lg font-semibold text-[var(--exa-gold)]">{value}</p>
    </div>
  );
}

export default InstructorDashboard;
