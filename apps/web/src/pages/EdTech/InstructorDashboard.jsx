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
    <main className="min-h-screen bg-[#0F1115] text-white">
      <header className="sticky top-0 z-30 border-b border-[#D4AF37]/20 bg-[#0F1115]/95 backdrop-blur">
        <div className="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
          <div>
            <h1 className="text-2xl font-semibold text-white">Instructor Dashboard</h1>
            <p className="text-sm text-[#9CA3AF]">Monitor performance, revenue, and student engagement.</p>
          </div>
          <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
            <button
              type="button"
              className="rounded-xl bg-gradient-to-r from-[#C9A227] via-[#D4AF37] to-[#F4D03F] px-4 py-2.5 text-sm font-semibold text-[#15100A] shadow-lg transition duration-300 hover:brightness-105 hover:shadow-[#D4AF37]/40"
            >
              + Create New Course
            </button>
            <button
              type="button"
              className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#D4AF37]/30 bg-[#1A1D24] text-[#D4AF37] transition duration-300 hover:border-[#D4AF37]/60"
              aria-label="Notifications"
            >
              <Bell className="h-4 w-4" />
            </button>
            <div className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[#D4AF37]/30 bg-[#1A1D24] text-xs font-semibold text-[#D4AF37]">
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
            className="mb-5 inline-flex items-center gap-2 rounded-xl border border-[#D4AF37]/30 bg-[#111827] px-4 py-2 text-sm font-semibold text-[#F4D03F] transition duration-300 hover:border-[#D4AF37]/60"
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
                className="rounded-xl border border-[#D4AF37]/20 bg-[#1A1D24] p-5 shadow-lg transition duration-300 hover:-translate-y-1 hover:border-[#D4AF37]/45"
              >
                <div className="flex items-start justify-between">
                  <div>
                    <p className="text-sm text-[#9CA3AF]">{item.label}</p>
                    <p className="mt-2 text-2xl font-semibold text-[#D4AF37]">{item.value}</p>
                  </div>
                  <span className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[#D4AF37]/30 bg-[#D4AF37]/10 text-[#D4AF37]">
                    <Icon className="h-5 w-5" />
                  </span>
                </div>
              </article>
            );
          })}
        </section>

        <section className="mt-8 rounded-xl border border-[#D4AF37]/20 bg-[#1A1D24] p-5 shadow-lg">
          <h2 className="text-xl font-semibold text-white">Revenue Analytics</h2>
          <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <StatItem label="Total Earnings" value="42.80 ETH" />
            <StatItem label="Pending Payout" value="1.24 ETH" />
            <StatItem label="NFT Certificate Revenue" value="6.12 ETH" />
            <StatItem label="Referral Revenue" value="2.08 ETH" />
          </div>
          <div className="mt-5 rounded-lg border border-[#D4AF37]/15 bg-[#111827] p-4">
            <p className="mb-3 text-xs uppercase tracking-wide text-[#D4AF37]/85">Revenue Trend (UI Placeholder)</p>
            <div className="h-28 rounded-md bg-gradient-to-r from-[#D4AF37]/5 via-[#D4AF37]/15 to-[#D4AF37]/5" />
          </div>
        </section>

        <section className="mt-8">
          <h2 className="text-xl font-semibold text-white">Course Performance</h2>
          <div className="mt-4 grid gap-4">
            {performanceCourses.map((course) => (
              <article
                key={course.id}
                className="rounded-xl border border-[#D4AF37]/20 bg-[#1A1D24] p-4 shadow-lg transition duration-300 hover:border-[#D4AF37]/45"
              >
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                  <div className="flex min-w-0 items-center gap-3">
                    <div className="h-14 w-14 rounded-lg border border-[#D4AF37]/20 bg-[#111827]" />
                    <div className="min-w-0">
                      <p className="truncate text-base font-semibold text-white">{course.title}</p>
                      <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-[#9CA3AF]">
                        <span>{course.students} students</span>
                        <span>{course.quizScore} avg quiz</span>
                        <span>{course.completion} completion</span>
                        <span className={`rounded-full border px-2 py-0.5 ${course.status === "Live" ? "border-[#D4AF37]/40 text-[#D4AF37]" : "border-neutral-600 text-neutral-300"}`}>
                          {course.status}
                        </span>
                      </div>
                    </div>
                  </div>
                  <div className="flex flex-col gap-2 sm:flex-row">
                    <button
                      type="button"
                      className="rounded-xl border border-[#D4AF37]/35 bg-transparent px-4 py-2 text-sm font-semibold text-[#F4D03F] transition duration-300 hover:bg-[#D4AF37]/10"
                    >
                      View Analytics
                    </button>
                    <button
                      type="button"
                      className="rounded-xl border border-[#D4AF37]/35 bg-transparent px-4 py-2 text-sm font-semibold text-[#F4D03F] transition duration-300 hover:bg-[#D4AF37]/10"
                    >
                      Manage Course
                    </button>
                  </div>
                </div>
              </article>
            ))}
          </div>
        </section>

        <section className="mt-8 rounded-xl border border-[#D4AF37]/20 bg-[#1A1D24] p-5 shadow-lg">
          <h2 className="text-xl font-semibold text-white">Recent Student Activity</h2>
          <div className="mt-4 overflow-x-auto">
            <table className="w-full min-w-[720px] text-left text-sm">
              <thead>
                <tr className="border-b border-[#D4AF37]/20 text-[#D4AF37]">
                  <th className="pb-3 font-semibold">Student Name</th>
                  <th className="pb-3 font-semibold">Course</th>
                  <th className="pb-3 font-semibold">Lesson Completed</th>
                  <th className="pb-3 font-semibold">Quiz Score</th>
                  <th className="pb-3 font-semibold">Status</th>
                </tr>
              </thead>
              <tbody>
                {studentActivity.map((row) => (
                  <tr key={`${row.name}-${row.course}`} className="border-b border-[#D4AF37]/10 text-[#9CA3AF]">
                    <td className="py-3">{row.name}</td>
                    <td className="py-3">{row.course}</td>
                    <td className="py-3">{row.lesson}</td>
                    <td className="py-3 text-[#D4AF37]">{row.quiz}</td>
                    <td className="py-3">{row.status}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        <section className="mt-8 grid gap-5 lg:grid-cols-2">
          <div className="rounded-xl border border-[#D4AF37]/20 bg-[#1A1D24] p-5 shadow-lg">
            <h2 className="text-xl font-semibold text-white">Instructor Announcements</h2>
            <div className="mt-4 space-y-3">
              <label className="block">
                <span className="mb-2 block text-xs text-[#9CA3AF]">Message</span>
                <textarea
                  rows={4}
                  placeholder="Write announcement to students..."
                  className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 placeholder:text-[#9CA3AF]/80 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
                />
              </label>
              <label className="block">
                <span className="mb-2 block text-xs text-[#9CA3AF]">Select Course</span>
                <select className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25">
                  {performanceCourses.map((course) => (
                    <option key={course.id} value={course.id} className="bg-[#111827] text-white">
                      {course.title}
                    </option>
                  ))}
                </select>
              </label>
              <label className="flex items-center gap-2 rounded-lg border border-[#D4AF37]/20 bg-[#111827] px-3 py-2.5 text-sm text-[#9CA3AF]">
                <input type="checkbox" className="h-4 w-4 rounded border-[#D4AF37]/40 bg-[#111827] text-[#D4AF37] focus:ring-[#D4AF37]/30" />
                Send to cohort
              </label>
              <button
                type="button"
                className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#C9A227] via-[#D4AF37] to-[#F4D03F] px-5 py-3 text-sm font-semibold text-[#15100A] shadow-lg transition duration-300 hover:brightness-105 hover:shadow-[#D4AF37]/40"
              >
                <Send className="h-4 w-4" />
                Send Announcement
              </button>
            </div>
          </div>

          <div className="space-y-5">
            <div className="rounded-xl border border-[#D4AF37]/20 bg-[#1A1D24] p-5 shadow-lg">
              <h2 className="text-xl font-semibold text-white">Engagement Analytics</h2>
              <div className="mt-4 grid gap-3 sm:grid-cols-2">
                {engagementStats.map((item) => (
                  <article key={item.label} className="rounded-lg border border-[#D4AF37]/15 bg-[#111827] p-3">
                    <p className="text-xs text-[#9CA3AF]">{item.label}</p>
                    <p className="mt-2 text-lg font-semibold text-[#D4AF37]">{item.value}</p>
                  </article>
                ))}
              </div>
            </div>

            <div className="rounded-xl border border-[#D4AF37]/20 bg-[#1A1D24] p-5 shadow-lg">
              <h2 className="text-xl font-semibold text-white">Quick Actions</h2>
              <div className="mt-4 grid gap-3 sm:grid-cols-2">
                {quickActions.map((action) => {
                  const Icon = action.icon;
                  return (
                    <button
                      key={action.label}
                      type="button"
                      className="rounded-lg border border-[#D4AF37]/20 bg-[#111827] p-3 text-left transition duration-300 hover:-translate-y-0.5 hover:border-[#D4AF37]/45"
                    >
                      <span className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-[#D4AF37]/30 bg-[#D4AF37]/10 text-[#D4AF37]">
                        <Icon className="h-4 w-4" />
                      </span>
                      <p className="mt-2 text-sm font-semibold text-white">{action.label}</p>
                    </button>
                  );
                })}
              </div>
            </div>
          </div>
        </section>

        <section className="mt-6 rounded-xl border border-[#D4AF37]/20 bg-[#111827]/75 p-4">
          <p className="border-l-2 border-[#D4AF37] pl-3 text-sm text-[#9CA3AF]">
            Your instructor dashboard is powered by ExaEarn decentralized infrastructure and encrypted blockchain-backed tracking.
          </p>
        </section>
      </div>
    </main>
  );
}

function StatItem({ label, value }) {
  return (
    <div className="rounded-lg border border-[#D4AF37]/15 bg-[#111827] p-3">
      <p className="text-xs text-[#9CA3AF]">{label}</p>
      <p className="mt-2 text-lg font-semibold text-[#D4AF37]">{value}</p>
    </div>
  );
}

export default InstructorDashboard;
