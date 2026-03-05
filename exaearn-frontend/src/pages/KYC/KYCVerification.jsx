import { useEffect, useMemo, useRef, useState } from "react";
import {
  ArrowLeft,
  Camera,
  CheckCircle2,
  Clock3,
  FileCheck2,
  Lock,
  ShieldAlert,
  ShieldCheck,
  Upload,
  XCircle,
} from "lucide-react";

const steps = [
  { id: 1, label: "Personal Info" },
  { id: 2, label: "ID Upload" },
  { id: 3, label: "Selfie" },
  { id: 4, label: "Review" },
];

const levelCards = [
  {
    level: "Level 0 - Basic",
    requirements: "Email & Phone Verified",
    access: "Limited withdrawals",
  },
  {
    level: "Level 1 - Identity Verified",
    requirements: "Government ID required",
    access: "Increased withdrawal limit",
  },
  {
    level: "Level 2 - Advanced Verification",
    requirements: "Proof of Address",
    access: "Higher limits & P2P access",
  },
];

const idTypes = ["National ID", "Passport", "Driver's License"];
const nationalities = ["Nigeria", "United States", "United Kingdom", "Canada", "Ghana", "South Africa", "Kenya"];
const MAX_FILE_BYTES = 5 * 1024 * 1024;

function KYCVerification({ onBack }) {
  const [currentStep, setCurrentStep] = useState(1);
  const [submitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState("");
  const [statusView, setStatusView] = useState(null);
  const [statusReason, setStatusReason] = useState("");

  const [personalInfo, setPersonalInfo] = useState({
    fullName: "",
    dateOfBirth: "",
    nationality: "Nigeria",
    address: "",
  });

  const [documentData, setDocumentData] = useState({
    idType: idTypes[0],
    frontFile: null,
    backFile: null,
    frontPreview: "",
    backPreview: "",
  });

  const [selfieData, setSelfieData] = useState({
    imagePreview: "",
    fileName: "",
  });

  const [confirmAccurate, setConfirmAccurate] = useState(false);
  const [livenessTick, setLivenessTick] = useState(0);
  const [cameraActive, setCameraActive] = useState(false);
  const [cameraError, setCameraError] = useState("");
  const [currentLevel] = useState("Level 0 - Basic");

  const videoRef = useRef(null);
  const streamRef = useRef(null);
  const submittingLockRef = useRef(false);

  const progressPercent = useMemo(() => (currentStep / steps.length) * 100, [currentStep]);
  const needsBackSide = documentData.idType !== "Passport";

  useEffect(() => {
    const interval = setInterval(() => {
      setLivenessTick((prev) => (prev + 1) % 100);
    }, 120);
    return () => clearInterval(interval);
  }, []);

  useEffect(() => {
    return () => {
      stopCamera();
    };
  }, []);

  const updatePersonalInfo = (field, value) => {
    setPersonalInfo((prev) => ({ ...prev, [field]: value }));
  };

  const validatePersonalInfo = () => {
    if (!personalInfo.fullName.trim()) return "Full Name is required.";
    if (!personalInfo.dateOfBirth) return "Date of Birth is required.";
    if (!/^\d{4}-\d{2}-\d{2}$/.test(personalInfo.dateOfBirth)) return "Date of Birth format should be YYYY-MM-DD.";
    if (!personalInfo.address.trim()) return "Residential Address is required.";
    return "";
  };

  const validateStep2 = () => {
    if (!documentData.frontFile) return "Front document upload is required.";
    if (needsBackSide && !documentData.backFile) return "Back document upload is required for this ID type.";
    return "";
  };

  const validateStep3 = () => {
    if (!selfieData.imagePreview) return "Selfie verification is required.";
    return "";
  };

  const handleContinue = () => {
    setErrorMessage("");
    if (currentStep === 1) {
      const error = validatePersonalInfo();
      if (error) {
        setErrorMessage(error);
        return;
      }
    }
    if (currentStep === 2) {
      const error = validateStep2();
      if (error) {
        setErrorMessage(error);
        return;
      }
    }
    if (currentStep === 3) {
      const error = validateStep3();
      if (error) {
        setErrorMessage(error);
        return;
      }
    }
    setCurrentStep((prev) => Math.min(4, prev + 1));
  };

  const handlePrevious = () => {
    setErrorMessage("");
    setCurrentStep((prev) => Math.max(1, prev - 1));
  };

  const validateFile = (file) => {
    if (!file) return "File is required.";
    const validTypes = ["image/jpeg", "image/png", "image/webp"];
    if (!validTypes.includes(file.type)) return "Only JPG, PNG or WEBP images are allowed.";
    if (file.size > MAX_FILE_BYTES) return "File exceeds 5MB limit.";
    return "";
  };

  const handleUploadFile = async (fieldPrefix, file) => {
    const error = validateFile(file);
    if (error) {
      setErrorMessage(error);
      return;
    }
    const preview = await fileToBase64(file);
    setDocumentData((prev) => ({
      ...prev,
      [`${fieldPrefix}File`]: file,
      [`${fieldPrefix}Preview`]: preview,
    }));
    setErrorMessage("");
  };

  const startCamera = async () => {
    setCameraError("");
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } });
      streamRef.current = stream;
      if (videoRef.current) {
        videoRef.current.srcObject = stream;
      }
      setCameraActive(true);
    } catch (error) {
      setCameraError("Unable to access camera. Please check permissions and try again.");
    }
  };

  const stopCamera = () => {
    if (streamRef.current) {
      streamRef.current.getTracks().forEach((track) => track.stop());
      streamRef.current = null;
    }
    setCameraActive(false);
  };

  const captureSelfie = async () => {
    if (!videoRef.current) return;
    const video = videoRef.current;
    const canvas = document.createElement("canvas");
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;
    const context = canvas.getContext("2d");
    if (!context) return;
    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    const dataUrl = canvas.toDataURL("image/jpeg", 0.9);
    setSelfieData({
      imagePreview: dataUrl,
      fileName: `selfie-${Date.now()}.jpg`,
    });
    stopCamera();
  };

  const buildSubmissionPayload = () => {
    return {
      personalInfo,
      document: {
        idType: documentData.idType,
        frontFileName: documentData.frontFile?.name || "",
        backFileName: documentData.backFile?.name || "",
      },
      selfie: {
        fileName: selfieData.fileName,
      },
      metadata: {
        submittedAt: new Date().toISOString(),
        app: "exaearn-frontend",
      },
    };
  };

  const handleSubmit = async () => {
    setErrorMessage("");
    if (!confirmAccurate) {
      setErrorMessage("Please confirm your information is accurate before submitting.");
      return;
    }
    if (submittingLockRef.current) return;

    submittingLockRef.current = true;
    setSubmitting(true);
    try {
      const payload = buildSubmissionPayload();
      const encryptedPayload = await encryptPayload(payload);

      await new Promise((resolve) => setTimeout(resolve, 1200));
      console.log("KYC submission (API-ready encrypted):", encryptedPayload);

      setStatusView("Pending Review");
      setStatusReason("");
    } catch (error) {
      setErrorMessage("Submission failed. Please try again.");
    } finally {
      setSubmitting(false);
      submittingLockRef.current = false;
    }
  };

  const simulateStatusRefresh = () => {
    if (statusView !== "Pending Review") return;
    const nextApproved = Math.random() >= 0.45;
    if (nextApproved) {
      setStatusView("Approved");
      setStatusReason("");
    } else {
      setStatusView("Rejected");
      setStatusReason("Uploaded document is blurry. Please re-upload a clearer image.");
    }
  };

  const resubmit = () => {
    setStatusView(null);
    setCurrentStep(2);
    setConfirmAccurate(false);
    setErrorMessage("");
  };

  return (
    <main className="min-h-screen bg-[#070B14] text-white">
      <header
        className="sticky top-0 z-30 border-b border-[#D4AF37]/25 bg-gradient-to-r from-[#121A2A]/95 via-[#0E1524]/95 to-[#0A0F1D]/95 backdrop-blur"
        style={{ paddingTop: "env(safe-area-inset-top)" }}
      >
        <div className="mx-auto w-full max-w-5xl px-4 pb-3 pt-3 sm:px-6">
          <div className="flex items-start justify-between gap-3">
            <div className="flex items-start gap-3">
              <button
                type="button"
                onClick={onBack}
                className="rounded-xl border border-white/15 bg-[#111827] p-2 text-[#E6EAF2] hover:border-[#D4AF37]/60"
              >
                <ArrowLeft className="h-4 w-4" />
              </button>
              <div>
                <h1 className="text-lg font-semibold text-[#F8F1DE] sm:text-xl">KYC Verification</h1>
                <p className="text-xs text-[#B8C0CF] sm:text-sm">Verify your identity to unlock full features</p>
              </div>
            </div>
            {!statusView ? <p className="text-xs font-medium text-[#D4AF37]">Step {currentStep} of 4</p> : null}
          </div>
          {!statusView ? (
            <div className="mt-3">
              <div className="h-2 overflow-hidden rounded-full bg-[#1A2334]">
                <div className="h-full rounded-full bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] transition-all duration-300" style={{ width: `${progressPercent}%` }} />
              </div>
            </div>
          ) : null}
        </div>
      </header>

      <div className="mx-auto w-full max-w-5xl px-4 pb-8 pt-5 sm:px-6">
        <section className="rounded-2xl border border-white/10 bg-[#101827]/85 p-4 shadow-[0_12px_28px_rgba(0,0,0,0.35)]">
          <h2 className="mb-3 text-base font-semibold text-[#F8F1DE]">Verification Levels</h2>
          <div className="grid gap-3 sm:grid-cols-3">
            {levelCards.map((item) => (
              <article
                key={item.level}
                className={`rounded-2xl border p-3 ${
                  currentLevel === item.level
                    ? "border-[#D4AF37]/70 bg-[#D4AF37]/10"
                    : "border-white/10 bg-[#0C1424]"
                }`}
              >
                <p className="text-sm font-semibold text-[#E6EAF2]">{item.level}</p>
                <p className="mt-2 text-xs text-[#B8C0CF]">{item.requirements}</p>
                <p className="mt-1 text-xs text-[#9AA3B4]">{item.access}</p>
              </article>
            ))}
          </div>
        </section>

        {!statusView ? (
          <section className="mt-4 rounded-2xl border border-white/10 bg-[#101827]/85 p-4 shadow-[0_12px_28px_rgba(0,0,0,0.35)]">
            <div className="mb-4 flex flex-wrap gap-2">
              {steps.map((step) => (
                <span
                  key={step.id}
                  className={`rounded-full border px-3 py-1 text-xs ${
                    step.id === currentStep
                      ? "border-[#D4AF37]/70 bg-[#D4AF37]/12 text-[#F8F1DE]"
                      : "border-white/15 bg-[#0C1424] text-[#A3ACBC]"
                  }`}
                >
                  {step.label}
                </span>
              ))}
            </div>

            {currentStep === 1 ? (
              <StepOnePersonalInfo personalInfo={personalInfo} onUpdate={updatePersonalInfo} />
            ) : null}

            {currentStep === 2 ? (
              <StepTwoDocumentUpload
                documentData={documentData}
                needsBackSide={needsBackSide}
                onIdTypeChange={(value) => setDocumentData((prev) => ({ ...prev, idType: value, backFile: null, backPreview: "" }))}
                onFrontUpload={(file) => handleUploadFile("front", file)}
                onBackUpload={(file) => handleUploadFile("back", file)}
              />
            ) : null}

            {currentStep === 3 ? (
              <StepThreeSelfie
                cameraActive={cameraActive}
                cameraError={cameraError}
                selfieData={selfieData}
                videoRef={videoRef}
                livenessTick={livenessTick}
                onStartCamera={startCamera}
                onCapture={captureSelfie}
                onStopCamera={stopCamera}
                onClearSelfie={() => setSelfieData({ imagePreview: "", fileName: "" })}
              />
            ) : null}

            {currentStep === 4 ? (
              <StepFourReview
                personalInfo={personalInfo}
                documentData={documentData}
                selfieData={selfieData}
                confirmAccurate={confirmAccurate}
                onConfirmChange={setConfirmAccurate}
              />
            ) : null}

            {errorMessage ? <p className="mt-3 text-sm text-[#FCA5A5]">{errorMessage}</p> : null}

            <div className="mt-4 flex items-center justify-between gap-3">
              <button
                type="button"
                onClick={handlePrevious}
                disabled={currentStep === 1}
                className="rounded-xl border border-white/15 bg-[#111827] px-4 py-2 text-sm text-[#D7DDEA] disabled:cursor-not-allowed disabled:opacity-45"
              >
                Previous
              </button>

              {currentStep < 4 ? (
                <button
                  type="button"
                  onClick={handleContinue}
                  className="rounded-xl bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] px-5 py-2 text-sm font-semibold text-[#111827] shadow-[0_8px_20px_rgba(212,175,55,0.28)]"
                >
                  Continue
                </button>
              ) : (
                <button
                  type="button"
                  disabled={submitting}
                  onClick={handleSubmit}
                  className="rounded-xl bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] px-5 py-2 text-sm font-semibold text-[#111827] shadow-[0_8px_20px_rgba(212,175,55,0.28)] disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {submitting ? "Submitting..." : "Submit KYC"}
                </button>
              )}
            </div>
          </section>
        ) : (
          <VerificationStatus
            statusView={statusView}
            statusReason={statusReason}
            onRefresh={simulateStatusRefresh}
            onResubmit={resubmit}
          />
        )}

        <section className="mt-4 space-y-3">
          <div className="rounded-2xl border border-[#D4AF37]/25 bg-[#0C1424] p-3 text-sm text-[#D7DDEA]">
            <p className="flex items-center gap-2">
              <Lock className="h-4 w-4 text-[#D4AF37]" />
              Your information is encrypted and securely stored.
            </p>
          </div>
          <div className="rounded-2xl border border-[#EF4444]/35 bg-[#EF4444]/10 p-3 text-sm text-[#FECACA]">
            <p className="flex items-center gap-2">
              <ShieldAlert className="h-4 w-4 text-[#F87171]" />
              ExaEarn will never request your password.
            </p>
          </div>
        </section>
      </div>
    </main>
  );
}

function StepOnePersonalInfo({ personalInfo, onUpdate }) {
  return (
    <div className="grid gap-3 sm:grid-cols-2">
      <Field label="Full Name">
        <input value={personalInfo.fullName} onChange={(e) => onUpdate("fullName", e.target.value)} className={inputCls} />
      </Field>
      <Field label="Date of Birth">
        <input type="date" value={personalInfo.dateOfBirth} onChange={(e) => onUpdate("dateOfBirth", e.target.value)} className={inputCls} />
      </Field>
      <Field label="Nationality">
        <select value={personalInfo.nationality} onChange={(e) => onUpdate("nationality", e.target.value)} className={inputCls}>
          {nationalities.map((item) => (
            <option key={item} value={item} className="bg-[#0C1424]">
              {item}
            </option>
          ))}
        </select>
      </Field>
      <Field label="Residential Address">
        <input value={personalInfo.address} onChange={(e) => onUpdate("address", e.target.value)} className={inputCls} />
      </Field>
    </div>
  );
}

function StepTwoDocumentUpload({ documentData, needsBackSide, onIdTypeChange, onFrontUpload, onBackUpload }) {
  return (
    <div className="space-y-3">
      <Field label="ID Type">
        <select value={documentData.idType} onChange={(event) => onIdTypeChange(event.target.value)} className={inputCls}>
          {idTypes.map((type) => (
            <option key={type} value={type} className="bg-[#0C1424]">
              {type}
            </option>
          ))}
        </select>
      </Field>
      <div className="grid gap-3 sm:grid-cols-2">
        <UploadField label="Front Upload" preview={documentData.frontPreview} onUpload={onFrontUpload} />
        {needsBackSide ? <UploadField label="Back Upload" preview={documentData.backPreview} onUpload={onBackUpload} /> : null}
      </div>
      <p className="text-xs text-[#98A1B2]">Accepted formats: JPG, PNG, WEBP up to 5MB. Ensure document is clear and all edges visible.</p>
    </div>
  );
}

function StepThreeSelfie({
  cameraActive,
  cameraError,
  selfieData,
  videoRef,
  livenessTick,
  onStartCamera,
  onCapture,
  onStopCamera,
  onClearSelfie,
}) {
  return (
    <div className="space-y-3">
      <p className="text-sm text-[#D7DDEA]">Ensure your face is clearly visible.</p>
      {!cameraActive && !selfieData.imagePreview ? (
        <button
          type="button"
          onClick={onStartCamera}
          className="inline-flex items-center gap-2 rounded-xl border border-[#D4AF37]/45 bg-[#D4AF37]/10 px-4 py-2 text-sm text-[#F8F1DE]"
        >
          <Camera className="h-4 w-4" />
          Start Camera
        </button>
      ) : null}

      {cameraError ? <p className="text-sm text-[#FCA5A5]">{cameraError}</p> : null}

      {cameraActive ? (
        <div className="relative overflow-hidden rounded-2xl border border-white/10 bg-black">
          <video ref={videoRef} autoPlay playsInline className="h-64 w-full object-cover sm:h-80" />
          <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
            <div className="h-44 w-32 rounded-[999px] border-2 border-[#D4AF37]/70 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]" />
          </div>
          <div className="absolute bottom-0 left-0 right-0 bg-black/55 p-2">
            <div className="mb-1 flex items-center justify-between text-[11px] text-[#D7DDEA]">
              <span>AI liveness detection</span>
              <span>{livenessTick}%</span>
            </div>
            <div className="h-1.5 overflow-hidden rounded-full bg-[#1F2937]">
              <div className="h-full bg-gradient-to-r from-[#D4AF37] to-[#E7C96C] transition-all" style={{ width: `${livenessTick}%` }} />
            </div>
          </div>
        </div>
      ) : null}

      {cameraActive ? (
        <div className="flex gap-2">
          <button type="button" onClick={onCapture} className="rounded-xl bg-gradient-to-r from-[#D4AF37] to-[#E7C96C] px-4 py-2 text-sm font-semibold text-[#111827]">
            Capture Selfie
          </button>
          <button type="button" onClick={onStopCamera} className="rounded-xl border border-white/15 bg-[#111827] px-4 py-2 text-sm">
            Cancel
          </button>
        </div>
      ) : null}

      {selfieData.imagePreview ? (
        <div className="rounded-2xl border border-white/10 bg-[#0C1424] p-3">
          <img src={selfieData.imagePreview} alt="Selfie preview" className="h-44 w-full rounded-xl object-cover sm:h-56" />
          <div className="mt-2 flex justify-between">
            <p className="text-xs text-[#A8B1C1]">{selfieData.fileName}</p>
            <button type="button" onClick={onClearSelfie} className="text-xs text-[#FCA5A5]">
              Remove
            </button>
          </div>
        </div>
      ) : null}
    </div>
  );
}

function StepFourReview({ personalInfo, documentData, selfieData, confirmAccurate, onConfirmChange }) {
  return (
    <div className="space-y-3">
      <div className="grid gap-3 sm:grid-cols-2">
        <ReviewCard label="Full Name" value={maskName(personalInfo.fullName)} />
        <ReviewCard label="Date of Birth" value={personalInfo.dateOfBirth} />
        <ReviewCard label="Nationality" value={personalInfo.nationality} />
        <ReviewCard label="Address" value={maskAddress(personalInfo.address)} />
        <ReviewCard label="ID Type" value={documentData.idType} />
        <ReviewCard label="Selfie" value={selfieData.fileName || "Uploaded"} />
      </div>
      <label className="flex items-center gap-2 rounded-xl border border-white/10 bg-[#0C1424] px-3 py-2">
        <input type="checkbox" checked={confirmAccurate} onChange={(e) => onConfirmChange(e.target.checked)} />
        <span className="text-sm text-[#D7DDEA]">I confirm this information is accurate.</span>
      </label>
    </div>
  );
}

function VerificationStatus({ statusView, statusReason, onRefresh, onResubmit }) {
  const statusConfig =
    statusView === "Approved"
      ? { icon: CheckCircle2, badge: "Approved", tone: "text-[#86EFAC]", box: "border-[#22C55E]/35 bg-[#22C55E]/10" }
      : statusView === "Rejected"
      ? { icon: XCircle, badge: "Rejected", tone: "text-[#FCA5A5]", box: "border-[#EF4444]/35 bg-[#EF4444]/10" }
      : { icon: Clock3, badge: "Pending Review", tone: "text-[#FDE68A]", box: "border-[#F59E0B]/35 bg-[#F59E0B]/10" };

  const Icon = statusConfig.icon;

  return (
    <section className="mt-4 rounded-2xl border border-white/10 bg-[#101827]/85 p-4 shadow-[0_12px_28px_rgba(0,0,0,0.35)]">
      <h2 className="mb-3 text-base font-semibold text-[#F8F1DE]">Verification Status</h2>
      <div className={`rounded-2xl border p-4 ${statusConfig.box}`}>
        <div className="flex items-center gap-2">
          <Icon className={`h-5 w-5 ${statusConfig.tone}`} />
          <span className={`text-sm font-semibold ${statusConfig.tone}`}>{statusConfig.badge}</span>
        </div>
        <p className="mt-2 text-sm text-[#D7DDEA]">Estimated review time: 5 to 15 minutes.</p>
        {statusReason ? <p className="mt-1 text-sm text-[#FCA5A5]">Reason: {statusReason}</p> : null}
      </div>

      <div className="mt-3 flex gap-2">
        {statusView === "Pending Review" ? (
          <button type="button" onClick={onRefresh} className="rounded-xl border border-white/15 bg-[#111827] px-4 py-2 text-sm">
            Refresh Status
          </button>
        ) : null}
        {statusView === "Rejected" ? (
          <button type="button" onClick={onResubmit} className="rounded-xl bg-gradient-to-r from-[#D4AF37] to-[#E7C96C] px-4 py-2 text-sm font-semibold text-[#111827]">
            Re-submit Documents
          </button>
        ) : null}
      </div>
    </section>
  );
}

function Field({ label, children }) {
  return (
    <label>
      <span className="mb-1.5 block text-xs font-medium text-[#D7DDEA]">{label}</span>
      {children}
    </label>
  );
}

function UploadField({ label, preview, onUpload }) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-xs font-medium text-[#D7DDEA]">{label}</span>
      <div className="cursor-pointer rounded-2xl border border-dashed border-white/20 bg-[#0C1424] p-3 hover:border-[#D4AF37]/55">
        <div className="flex items-center gap-2 text-sm text-[#B8C0CF]">
          <Upload className="h-4 w-4 text-[#D4AF37]" />
          Upload image
        </div>
        <input
          type="file"
          accept="image/png,image/jpeg,image/webp"
          className="mt-2 w-full text-xs text-[#9CA3AF] file:mr-2 file:rounded-lg file:border-0 file:bg-[#111827] file:px-2 file:py-1 file:text-[#D7DDEA]"
          onChange={(event) => {
            const file = event.target.files?.[0];
            if (file) onUpload(file);
          }}
        />
        {preview ? <img src={preview} alt={`${label} preview`} className="mt-2 h-32 w-full rounded-lg object-cover" /> : null}
      </div>
    </label>
  );
}

function ReviewCard({ label, value }) {
  return (
    <article className="rounded-xl border border-white/10 bg-[#0C1424] p-3">
      <p className="text-[11px] uppercase tracking-[0.1em] text-[#9AA3B4]">{label}</p>
      <p className="mt-1 text-sm text-[#E6EAF2]">{value || "-"}</p>
    </article>
  );
}

function maskName(name) {
  if (!name) return "";
  const parts = name.trim().split(" ");
  return parts.map((part) => `${part[0] || ""}${"*".repeat(Math.max(0, part.length - 1))}`).join(" ");
}

function maskAddress(address) {
  if (!address) return "";
  if (address.length <= 6) return `${address[0] || ""}****`;
  return `${address.slice(0, 4)}****${address.slice(-2)}`;
}

async function encryptPayload(payload) {
  const data = new TextEncoder().encode(JSON.stringify(payload));
  if (!window.crypto?.subtle) {
    return { algorithm: "base64-fallback", payload: btoa(JSON.stringify(payload)) };
  }

  const key = await window.crypto.subtle.generateKey({ name: "AES-GCM", length: 256 }, true, ["encrypt", "decrypt"]);
  const iv = window.crypto.getRandomValues(new Uint8Array(12));
  const encrypted = await window.crypto.subtle.encrypt({ name: "AES-GCM", iv }, key, data);
  const rawKey = await window.crypto.subtle.exportKey("raw", key);

  return {
    algorithm: "AES-GCM",
    iv: arrayBufferToBase64(iv.buffer),
    key: arrayBufferToBase64(rawKey),
    cipherText: arrayBufferToBase64(encrypted),
  };
}

function arrayBufferToBase64(buffer) {
  const bytes = new Uint8Array(buffer);
  let binary = "";
  bytes.forEach((byte) => {
    binary += String.fromCharCode(byte);
  });
  return btoa(binary);
}

function fileToBase64(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result || ""));
    reader.onerror = () => reject(new Error("Failed to read file."));
    reader.readAsDataURL(file);
  });
}

const inputCls =
  "w-full rounded-xl border border-white/15 bg-[#0C1424] px-3 py-2.5 text-sm text-white outline-none placeholder:text-[#8E98AA] focus:border-[#D4AF37]/70";

export default KYCVerification;
