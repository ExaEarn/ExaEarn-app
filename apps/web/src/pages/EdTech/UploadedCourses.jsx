import { ArrowLeft, ArrowRight, BookOpen, Clock3, Layers, Sparkles } from "lucide-react";
import Image from "../../assets/Image";

const uploadedCourses = [
  {
    id: "web3-fundamentals",
    title: "Web3 Fundamentals",
    level: "Beginner",
    duration: "6 hrs",
    lessons: 18,
    reward: "250 EXA",
  },
  {
    id: "defi-strategy-lab",
    title: "DeFi Strategy Lab",
    level: "Intermediate",
    duration: "5 hrs",
    lessons: 14,
    reward: "420 EXA",
  },
  {
    id: "rwa-tokenization",
    title: "RWA & Tokenization",
    level: "Advanced",
    duration: "4 hrs",
    lessons: 10,
    reward: "600 EXA",
  },
  {
    id: "web-development",
    title: "Web Development",
    level: "Beginner",
    duration: "8 hrs",
    lessons: 20,
    reward: "320 EXA",
  },
];

function UploadedCourses({ onBack, onOpenCourse = () => {} }) {
  return (
    <main className="min-h-screen text-[var(--exa-text-primary)] exa-bg app-shell">
      <div className="container mx-auto w-full max-w-sm px-3 pb-6 pt-4 sm:max-w-lg sm:px-4 sm:pt-6 md:max-w-2xl lg:max-w-5xl xl:max-w-6xl">
        <div className="rounded-3xl p-4 shadow-xl glass-card sm:p-6">
          <header className="rounded-3xl border border-[var(--exa-border)] bg-gradient-to-br from-[#0B0B0B]/95 via-[#151515]/90 to-[#0B0B0B]/95 p-5">
            <div className="flex items-center justify-between gap-3">
              <div>
                <p className="inline-flex items-center gap-2 rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-[var(--exa-gold)]">
                  <Sparkles className="h-3.5 w-3.5" />
                  ExaEarn Courses
                </p>
                <h1 className="mt-3 font-['Sora'] text-3xl font-semibold text-[#F8F8F8] sm:text-4xl">Uploaded Courses</h1>
                <p className="mt-2 text-sm text-[#F8F8F8]/70">
                  Explore premium courses uploaded on ExaEarn and start earning rewards.
                </p>
              </div>
              {onBack ? (
                <button type="button" onClick={onBack} className="btn-outline inline-flex items-center gap-2">
                  <ArrowLeft className="h-4 w-4" />
                  Back
                </button>
              ) : null}
            </div>
          </header>

          <section className="mt-6 grid gap-4 sm:grid-cols-2">
            {uploadedCourses.map((course) => (
              <article
                key={course.id}
                className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-soft)] transition-all duration-300 hover:-translate-y-1 hover:border-[var(--exa-border-active)] hover:shadow-[var(--exa-shadow-gold)]"
              >
                <div className="relative h-32 overflow-hidden rounded-xl">
                  <img src={Image.edu} alt={course.title} className="h-full w-full object-cover opacity-80" />
                  <div className="absolute inset-0 bg-gradient-to-t from-[#0B0B0B]/85 via-transparent to-transparent" />
                  <span className="absolute right-2 top-2 rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-2 py-0.5 text-[10px] font-semibold text-[var(--exa-gold)]">
                    {course.level}
                  </span>
                </div>

                <h2 className="mt-3 text-base font-semibold text-[#F8F8F8]">{course.title}</h2>

                <div className="mt-3 grid grid-cols-2 gap-2 text-xs text-[#F8F8F8]/75">
                  <p className="inline-flex items-center gap-1 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-2 py-1">
                    <Clock3 className="h-3.5 w-3.5 text-[var(--exa-gold)]" />
                    {course.duration}
                  </p>
                  <p className="inline-flex items-center gap-1 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-2 py-1">
                    <Layers className="h-3.5 w-3.5 text-[var(--exa-gold)]" />
                    {course.lessons} lessons
                  </p>
                  <p className="col-span-2 inline-flex items-center gap-1 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-gold-surface)] px-2 py-1 font-semibold text-[var(--exa-gold)]">
                    <BookOpen className="h-3.5 w-3.5" />
                    Reward: {course.reward}
                  </p>
                </div>

                <button type="button" onClick={() => onOpenCourse(course.id)} className="btn-gold mt-4 w-full">
                  Start Course
                  <ArrowRight className="h-4 w-4" />
                </button>
              </article>
            ))}
          </section>
        </div>
      </div>
    </main>
  );
}

export default UploadedCourses;
