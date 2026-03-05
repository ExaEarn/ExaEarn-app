import { LoaderCircle } from "lucide-react";

function ActionButton({ isLoading, disabled, onClick, loadingText = "Picking...", children }) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled || isLoading}
      className="game-primary-btn mx-auto flex min-h-14 w-full max-w-xs items-center justify-center gap-3 rounded-full px-6 py-3 text-xl font-semibold text-cosmic-900 transition-all duration-300 hover:scale-[1.02] hover:shadow-button-glow active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60 disabled:shadow-none sm:max-w-sm"
    >
      {isLoading ? (
        <>
          <LoaderCircle className="h-5 w-5 animate-spin" aria-hidden="true" />
          {loadingText}
        </>
      ) : (
        children
      )}
    </button>
  );
}

export default ActionButton;
