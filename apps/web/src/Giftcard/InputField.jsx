function InputField({
  id,
  label,
  value,
  onChange,
  placeholder,
  type = "text",
  error,
  children,
  ...rest
}) {
  return (
    <div>
      <label htmlFor={id} className="mb-2 block text-sm font-medium tracking-wide text-[var(--exa-text-secondary)]">
        {label}
      </label>
      <div className={`gift-input-wrap flex items-center gap-2 rounded-xl px-4 py-3 ${error ? "gift-input-error" : ""}`}>
        <input
          id={id}
          type={type}
          value={value}
          onChange={onChange}
          placeholder={placeholder}
          className="w-full bg-transparent text-base text-[var(--exa-text-primary)] outline-none placeholder:text-[var(--exa-text-muted)]"
          {...rest}
        />
        {children}
      </div>
      {error ? <p className="mt-1 text-xs text-rose-300">{error}</p> : null}
    </div>
  );
}

export default InputField;
