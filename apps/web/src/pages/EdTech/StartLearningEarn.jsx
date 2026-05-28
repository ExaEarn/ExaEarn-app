import {
  ArrowLeft,
  ArrowRight,
  Award,
  BadgeCheck,
  BookOpen,
  Briefcase,
  CheckCircle2,
  Coins,
  FileCheck2,
  Link2,
  Milestone,
  Sparkles,
  Target,
  TrendingUp,
  UserPlus,
} from "lucide-react";
import Image from "../../assets/Image";

const courseCards = [
  { id: "course-1", title: "Web3 Fundamentals", level: "Beginner", duration: "6 Hours", reward: "250 EXA" },
  { id: "course-2", title: "AI for Modern Business", level: "Intermediate", duration: "8 Hours", reward: "420 EXA" },
  { id: "course-3", title: "Global Product Strategy", level: "Advanced", duration: "7 Hours", reward: "600 EXA" },
];

const learningSteps = [
  {
    id: "step-1",
    icon: BookOpen,
    title: "Enroll in High-Value Courses",
    text: "Choose from Web3, Tech, Business, AgriTech, AI, and emerging global opportunities.",
  },
  {
    id: "step-2",
    icon: Target,
    title: "Complete Lessons + Real Tasks",
    text: "Each lesson includes quizzes and practical assignments. Unlock the next lesson after passing.",
  },
  {
    id: "step-3",
    icon: BadgeCheck,
    title: "Earn EXA + Get Proof of Skill Certificate",
    text: "Receive EXA token rewards and a blockchain-backed certificate upon completion.",
  },
];

const rewardItems = [
  "Earn EXA tokens for completing lessons",
  "Bonus EXA for top quiz scores",
  "Referral rewards",
  "Milestone achievement bonuses",
];

function StartLearningEarn({ onBack, onExploreCourses = () => {}, onStartCourse = () => {}, onCreateAccount = () => {} }) {
  return (
    <main className="min-h-screen text-white exa-bg app-shell">
      <div className="container mx-auto w-full max-w-sm px-3 pb-6 pt-4 sm:max-w-lg sm:px-4 sm:pt-6 md:max-w-2xl lg:max-w-5xl xl:max-w-6xl">
        <div className="rounded-3xl p-4 shadow-xl glass-card sm:p-6">
          <header className="rounded-3xl border border-violet-300/20 bg-gradient-to-br from-cosmic-900/90 via-cosmic-800/70 to-cosmic-900/95 p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <p className="inline-flex items-center gap-2 rounded-full border border-auric-300/40 bg-auric-300/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-auric-300">
                <Sparkles className="h-3.5 w-3.5" />
                Start Learning & Earn with ExaEarn
              </p>
              {onBack ? (
                <button type="button" onClick={onBack} className="btn-outline inline-flex items-center gap-2">
                  <ArrowLeft className="h-4 w-4" />
                  Back to EdTech
                </button>
              ) : null}
            </div>

            <div className="mt-5 grid gap-5 lg:grid-cols-[1.14fr_0.86fr]">
              <div>
                <h1 className="font-['Sora'] text-3xl font-semibold leading-tight text-violet-50 sm:text-5xl">
                  Learn High-Value Skills. Earn EXA Rewards. Build Verifiable Proof of Skill.
                </h1>
                <p className="mt-3 max-w-3xl text-sm leading-relaxed text-violet-100/78 sm:text-base">
                  Access industry-standard courses, complete real-world tasks, earn EXA tokens, and receive blockchain-verifiable
                  certificates that prove your expertise.
                </p>
                <div className="mt-5 flex flex-wrap gap-3">
                  <button type="button" onClick={onStartCourse} className="btn-gold inline-flex items-center gap-2">
                    Start Learning
                    <ArrowRight className="h-4 w-4" />
                  </button>
                  <button type="button" onClick={onExploreCourses} className="btn-outline">
                    Explore Courses
                  </button>
                </div>
              </div>

              <aside className="grid gap-3">
                <div className="rounded-2xl border border-violet-300/20 bg-cosmic-900/70 p-4">
                  <p className="text-xs uppercase tracking-[0.18em] text-auric-300/85">Learner Snapshot</p>
                  <div className="mt-3 grid grid-cols-2 gap-2 text-xs text-violet-100/75">
                    <p className="rounded-xl border border-violet-300/20 bg-cosmic-900/80 px-3 py-2">Courses Enrolled: 5</p>
                    <p className="rounded-xl border border-auric-300/35 bg-auric-300/10 px-3 py-2 text-auric-200">EXA Earned: 1,280</p>
                    <p className="rounded-xl border border-violet-300/20 bg-cosmic-900/80 px-3 py-2">Tasks Completed: 41</p>
                    <p className="rounded-xl border border-auric-300/35 bg-auric-300/10 px-3 py-2 text-auric-200">Certificates: 3</p>
                  </div>
                </div>
                <div className="rounded-2xl border border-auric-300/25 bg-gradient-to-r from-auric-300/12 to-cosmic-900/35 p-4">
                  <p className="text-xs uppercase tracking-[0.16em] text-auric-300/85">Vision</p>
                  <p className="mt-2 text-sm font-semibold text-violet-50">
                    A global skill economy where learning outcomes are instantly provable and rewarded.
                  </p>
                </div>
              </aside>
            </div>
          </header>

          <section className="mt-6">
            <h2 className="font-['Sora'] text-2xl font-semibold text-violet-50 sm:text-3xl">How It Works</h2>
            <div className="mt-4 grid gap-4 md:grid-cols-3">
              {learningSteps.map((step, index) => {
                const Icon = step.icon;
                return (
                  <article
                    key={step.id}
                    className="rounded-2xl border border-violet-300/15 bg-cosmic-900/65 p-5 shadow-cosmic-card transition-all duration-300 hover:-translate-y-1 hover:border-auric-300/55 hover:shadow-button-glow"
                  >
                    <div className="flex items-center justify-between">
                      <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-auric-300/45 bg-auric-300/10 text-auric-300">
                        <Icon className="h-5 w-5" />
                      </span>
                      <span className="rounded-full border border-violet-300/25 bg-cosmic-900/80 px-2.5 py-0.5 text-xs font-semibold text-violet-100/80">
                        0{index + 1}
                      </span>
                    </div>
                    <h3 className="mt-3 text-base font-semibold text-violet-50">{step.title}</h3>
                    <p className="mt-2 text-sm text-violet-100/72">{step.text}</p>
                  </article>
                );
              })}
            </div>
          </section>

          <section className="mt-6">
            <div className="flex items-center justify-between gap-3">
              <h2 className="font-['Sora'] text-2xl font-semibold text-violet-50 sm:text-3xl">High-Impact Courses That Pay You Back</h2>
              <button type="button" onClick={onExploreCourses} className="text-xs font-semibold text-auric-300">
                View All
              </button>
            </div>
            <div className="mt-4 grid gap-4 md:grid-cols-3">
              {courseCards.map((course) => (
                <article
                  key={course.id}
                  className="rounded-2xl border border-violet-300/15 bg-cosmic-900/65 p-4 shadow-cosmic-card transition-all duration-300 hover:-translate-y-1 hover:border-auric-300/55 hover:shadow-button-glow"
                >
                  <div className="relative h-36 overflow-hidden rounded-xl">
                    <img src={Image.edu} alt={course.title} className="h-full w-full object-cover opacity-85" />
                    <div className="absolute inset-0 bg-gradient-to-t from-cosmic-900/85 via-transparent to-transparent" />
                  </div>
                  <h3 className="mt-3 text-base font-semibold text-violet-50">{course.title}</h3>
                  <div className="mt-2 grid grid-cols-2 gap-2 text-xs text-violet-100/75">
                    <p className="rounded-lg border border-violet-300/20 bg-cosmic-900/80 px-2 py-1">{course.level}</p>
                    <p className="rounded-lg border border-violet-300/20 bg-cosmic-900/80 px-2 py-1">{course.duration}</p>
                    <p className="col-span-2 rounded-lg border border-auric-300/30 bg-auric-300/10 px-2 py-1 font-semibold text-auric-300">
                      Reward: {course.reward}
                    </p>
                  </div>
                  <button type="button" onClick={onStartCourse} className="btn-gold mt-4 w-full">
                    Start Course
                  </button>
                </article>
              ))}
            </div>
          </section>

          <section className="mt-6 grid gap-4 lg:grid-cols-[1.05fr_0.95fr]">
            <article className="rounded-2xl border border-violet-300/15 bg-cosmic-900/65 p-5 shadow-cosmic-card">
              <h2 className="font-['Sora'] text-2xl font-semibold text-violet-50">Earn While You Learn</h2>
              <div className="mt-4 grid gap-2">
                {rewardItems.map((item) => (
                  <p
                    key={item}
                    className="flex items-center gap-2 rounded-xl border border-violet-300/15 bg-cosmic-900/70 px-3 py-2 text-sm text-violet-100/80"
                  >
                    <CheckCircle2 className="h-4 w-4 text-auric-300" />
                    {item}
                  </p>
                ))}
              </div>
            </article>

            <article className="relative overflow-hidden rounded-2xl border border-auric-300/30 bg-gradient-to-br from-cosmic-900/75 via-cosmic-800/75 to-cosmic-900/85 p-5 shadow-[0_0_30px_rgba(212,175,55,0.2)]">
              <div className="pointer-events-none absolute -right-3 top-5 h-16 w-16 animate-bounce rounded-full bg-auric-300/25 blur-sm" />
              <h3 className="text-lg font-semibold text-violet-50">EXA Reward Engine</h3>
              <div className="mt-4 grid grid-cols-2 gap-2 text-xs text-violet-100/78">
                <p className="rounded-lg border border-violet-300/20 bg-cosmic-900/80 px-3 py-2">Lesson Rewards</p>
                <p className="rounded-lg border border-violet-300/20 bg-cosmic-900/80 px-3 py-2">Quiz Multipliers</p>
                <p className="rounded-lg border border-violet-300/20 bg-cosmic-900/80 px-3 py-2">Referral Boost</p>
                <p className="rounded-lg border border-violet-300/20 bg-cosmic-900/80 px-3 py-2">Milestone Bonus</p>
              </div>
              <div className="mt-4 inline-flex items-center gap-2 rounded-full border border-auric-300/40 bg-auric-300/10 px-3 py-1.5 text-xs font-semibold text-auric-300">
                <Coins className="h-4 w-4" />
                Token distribution updates in real time
              </div>
            </article>
          </section>

          <section className="mt-6 grid gap-4 lg:grid-cols-[1fr_1fr]">
            <article className="rounded-2xl border border-violet-300/15 bg-cosmic-900/65 p-5 shadow-cosmic-card">
              <h2 className="font-['Sora'] text-2xl font-semibold text-violet-50">Verifiable. Shareable. Powerful.</h2>
              <div className="mt-4 grid gap-2">
                <p className="flex items-center gap-2 text-sm text-violet-100/78">
                  <FileCheck2 className="h-4 w-4 text-auric-300" />
                  Receive a digital Proof-of-Skill certificate
                </p>
                <p className="flex items-center gap-2 text-sm text-violet-100/78">
                  <Link2 className="h-4 w-4 text-auric-300" />
                  Blockchain-verifiable
                </p>
                <p className="flex items-center gap-2 text-sm text-violet-100/78">
                  <Briefcase className="h-4 w-4 text-auric-300" />
                  Public portfolio link
                </p>
                <p className="flex items-center gap-2 text-sm text-violet-100/78">
                  <TrendingUp className="h-4 w-4 text-auric-300" />
                  Share on LinkedIn, Twitter, and Web3 platforms
                </p>
              </div>
            </article>

            <article className="rounded-2xl border border-auric-300/30 bg-gradient-to-br from-cosmic-900/90 via-cosmic-800/80 to-cosmic-900/95 p-5 text-violet-50 shadow-[0_0_30px_rgba(212,175,55,0.18)]">
              <div className="rounded-2xl border border-violet-300/20 bg-cosmic-900/60 p-4">
                <p className="text-xs uppercase tracking-[0.18em] text-auric-300/85">Certificate Mockup</p>
                <h3 className="mt-2 text-lg font-semibold text-violet-50">ExaEarn Proof of Skill</h3>
                <div className="mt-3 grid grid-cols-2 gap-2 text-xs text-violet-100/80">
                  <p className="rounded-lg border border-violet-300/20 bg-cosmic-900/80 px-2 py-1">Learner ID: EXA-24517</p>
                  <p className="rounded-lg border border-violet-300/20 bg-cosmic-900/80 px-2 py-1">Status: Verified</p>
                  <p className="rounded-lg border border-violet-300/20 bg-cosmic-900/80 px-2 py-1">Course: Web3 Fundamentals</p>
                  <p className="rounded-lg border border-violet-300/20 bg-cosmic-900/80 px-2 py-1">Reward: 250 EXA</p>
                </div>
                <div className="mt-4 inline-flex items-center gap-2 rounded-full border border-auric-300/45 bg-auric-300/10 px-3 py-1 text-xs font-semibold text-auric-300">
                  <Award className="h-4 w-4" />
                  Blockchain Signed Credential
                </div>
              </div>
            </article>
          </section>

          <section className="mt-6 rounded-2xl border border-violet-300/15 bg-cosmic-900/65 p-5 shadow-cosmic-card">
            <h2 className="font-['Sora'] text-2xl font-semibold text-violet-50">Student Dashboard Preview</h2>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
              <DashboardCard label="Progress" value="68%" />
              <DashboardCard label="EXA Earnings" value="1,280 EXA" highlight />
              <DashboardCard label="Completed Lessons" value="41" />
              <DashboardCard label="Upcoming Quizzes" value="3" highlight />
              <DashboardCard label="Certificate Status" value="2 Issued" />
            </div>
            <div className="mt-5">
              <p className="text-xs font-semibold uppercase tracking-[0.16em] text-auric-300/80">Weekly Completion</p>
              <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-violet-300/20">
                <div className="h-full w-[72%] rounded-full bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500" />
              </div>
            </div>
          </section>

          <section className="mt-6 rounded-3xl border border-auric-300/35 bg-gradient-to-r from-cosmic-900/90 via-cosmic-800/85 to-cosmic-900/90 p-6 shadow-[0_0_36px_rgba(212,175,55,0.22)] sm:p-8">
            <h2 className="font-['Sora'] text-3xl font-semibold leading-tight text-violet-50 sm:text-4xl">Your Knowledge Should Pay You.</h2>
            <div className="mt-5 flex flex-wrap gap-3">
              <button type="button" onClick={onStartCourse} className="btn-gold inline-flex items-center gap-2">
                Start Learning Now
                <ArrowRight className="h-4 w-4" />
              </button>
              <button type="button" onClick={onCreateAccount} className="btn-outline inline-flex items-center gap-2">
                Create Free Account
                <UserPlus className="h-4 w-4" />
              </button>
            </div>
          </section>
        </div>
      </div>
    </main>
  );
}

function DashboardCard({ label, value, highlight = false }) {
  return (
    <article
      className={`rounded-xl border px-3 py-3 ${
        highlight ? "border-auric-300/30 bg-auric-300/10 text-auric-200" : "border-violet-300/20 bg-cosmic-900/80 text-violet-100/80"
      }`}
    >
      <p className="text-xs opacity-80">{label}</p>
      <p className="mt-1 text-sm font-semibold">{value}</p>
    </article>
  );
}

export default StartLearningEarn;
