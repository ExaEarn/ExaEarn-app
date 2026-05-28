function GiftcardSelector({ providers, selectedProvider, onProviderChange, selectedCountry, onCountryChange, selectedDenomination, onDenominationChange, categories, selectedCategory, onCategoryChange, denominations }) {
  return (
    <article className="buy-card rounded-2xl p-5 sm:p-6">
      <h2 className="font-['Sora'] text-2xl font-semibold text-violet-50">Giftcard Selection</h2>
      <div className="mt-5 grid gap-4">
        <SelectField label="Giftcard Provider" value={selectedProvider} onChange={onProviderChange} options={providers} />
        <SelectField label="Giftcard Category" value={selectedCategory} onChange={onCategoryChange} options={categories} />
        <SelectField label="Country / Region" value={selectedCountry} onChange={onCountryChange} options={["United States", "United Kingdom", "Canada", "Nigeria"]} />
        <SelectField label="Amount / Denomination" value={selectedDenomination} onChange={onDenominationChange} options={denominations} />
      </div>
    </article>
  );
}

function SelectField({ label, value, onChange, options }) {
  return (
    <div>
      <label className="mb-2 block text-sm font-medium tracking-wide text-violet-100/85">{label}</label>
      <div className="buy-input-wrap rounded-xl px-4 py-3">
        <select value={value} onChange={onChange} className="w-full bg-transparent text-base text-violet-50 outline-none">
          {options.map((option) => (
            <option key={option} value={option} className="bg-cosmic-900 text-violet-50">
              {option}
            </option>
          ))}
        </select>
      </div>
    </div>
  );
}

export default GiftcardSelector;
