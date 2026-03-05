import { useMemo, useState } from "react";
import { Activity, ArrowLeft, Lock, ShieldCheck, Store } from "lucide-react";
import FilterBar from "./FilterBar";
import ListingModal from "./ListingModal";
import NFTCard from "./NFTCard";
import PurchaseModal from "./PurchaseModal";
import Tabs from "./Tabs";
import "./NFTMarketplace.css";

const tabs = [
  { id: "explore", label: "Explore" },
  { id: "myNfts", label: "My NFTs" },
  { id: "listed", label: "Listed for Sale" },
  { id: "activity", label: "Activity" },
];

const nftDataset = [
  {
    id: "nft-1",
    name: "Nebula Crown",
    collection: "Exa Genesis",
    category: "Art",
    priceEth: 0.82,
    owner: "0x4a...0c51",
    creator: "0x11...ab2",
    image: "https://images.unsplash.com/photo-1634973357973-f2ed2657db3c?auto=format&fit=crop&w=900&q=80",
    listed: true,
    popularScore: 94,
    createdAt: "2026-01-21",
  },
  {
    id: "nft-2",
    name: "Arc Pulse Avatar",
    collection: "Pulse",
    category: "Gaming",
    priceEth: 0.38,
    owner: "0x98...a07",
    creator: "0x13...bc3",
    image: "https://images.unsplash.com/photo-1620641788421-7a1c342ea42e?auto=format&fit=crop&w=900&q=80",
    listed: true,
    popularScore: 77,
    createdAt: "2026-02-05",
  },
  {
    id: "nft-3",
    name: "Signal Loop",
    collection: "Sonic Chain",
    category: "Music",
    priceEth: 1.46,
    owner: "0x4a...0c51",
    creator: "0x07...f90",
    image: "https://images.unsplash.com/photo-1516321497487-e288fb19713f?auto=format&fit=crop&w=900&q=80",
    listed: false,
    popularScore: 70,
    createdAt: "2025-12-14",
  },
  {
    id: "nft-4",
    name: "Node Access Pass",
    collection: "Protocol Keys",
    category: "Utility",
    priceEth: 0.24,
    owner: "0x4a...0c51",
    creator: "0x31...d13",
    image: "https://images.unsplash.com/photo-1618005198919-d3d4b5a92eee?auto=format&fit=crop&w=900&q=80",
    listed: false,
    popularScore: 62,
    createdAt: "2026-02-09",
  },
  {
    id: "nft-5",
    name: "Aurum Relic",
    collection: "Vault",
    category: "Collectible",
    priceEth: 2.3,
    owner: "0x59...be1",
    creator: "0x31...d13",
    image: "https://images.unsplash.com/photo-1642104704074-907c0698f7bd?auto=format&fit=crop&w=900&q=80",
    listed: true,
    popularScore: 89,
    createdAt: "2026-01-30",
  },
  {
    id: "nft-6",
    name: "Orbit Fragment",
    collection: "Exa Genesis",
    category: "Art",
    priceEth: 0.55,
    owner: "0x82...cc2",
    creator: "0x11...ab2",
    image: "https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=900&q=80",
    listed: true,
    popularScore: 81,
    createdAt: "2026-02-12",
  },
];

const activityFeed = [
  { id: "ac-1", type: "Purchase", item: "Nebula Crown", amount: "0.82 ETH", date: "2026-02-20" },
  { id: "ac-2", type: "Listing", item: "Aurum Relic", amount: "2.30 ETH", date: "2026-02-18" },
  { id: "ac-3", type: "Sale", item: "Orbit Fragment", amount: "0.55 ETH", date: "2026-02-15" },
];

function NFTMarketplace({ onBack }) {
  const [activeTab, setActiveTab] = useState("explore");
  const [search, setSearch] = useState("");
  const [category, setCategory] = useState("All");
  const [priceCap, setPriceCap] = useState("All");
  const [sortBy, setSortBy] = useState("Newest");
  const [selectedForPurchase, setSelectedForPurchase] = useState(null);
  const [selectedForListing, setSelectedForListing] = useState(null);

  const filteredExplore = useMemo(() => {
    const maxPrice =
      priceCap === "<= 0.50 ETH"
        ? 0.5
        : priceCap === "<= 1.00 ETH"
          ? 1
          : priceCap === "<= 2.00 ETH"
            ? 2
            : priceCap === "<= 5.00 ETH"
              ? 5
              : Infinity;

    const filtered = nftDataset.filter((item) => {
      const q = search.toLowerCase();
      const matchedSearch = !q || item.name.toLowerCase().includes(q) || item.collection.toLowerCase().includes(q) || item.creator.toLowerCase().includes(q);
      const matchedCategory = category === "All" || item.category === category;
      const matchedPrice = item.priceEth <= maxPrice;
      return matchedSearch && matchedCategory && matchedPrice;
    });

    return filtered.sort((a, b) => {
      if (sortBy === "Price: Low to High") return a.priceEth - b.priceEth;
      if (sortBy === "Price: High to Low") return b.priceEth - a.priceEth;
      if (sortBy === "Popular") return b.popularScore - a.popularScore;
      return new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime();
    });
  }, [search, category, priceCap, sortBy]);

  const myNfts = nftDataset.filter((item) => item.owner === "0x4a...0c51");
  const listed = nftDataset.filter((item) => item.listed);

  return (
    <main className="nft-bg min-h-screen px-4 py-10 sm:px-6 sm:py-12">
      <section className="nft-shell mx-auto w-full max-w-7xl rounded-[2rem] p-5 sm:p-8 lg:p-10">
        <header className="nft-card rounded-3xl p-6 sm:p-8">
          {onBack ? (
            <div className="mb-4 flex justify-start">
              <button type="button" onClick={onBack} className="btn-outline inline-flex items-center gap-2">
                <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                Back
              </button>
            </div>
          ) : null}
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="max-w-3xl">
              <h1 className="font-['Sora'] text-4xl font-semibold tracking-tight text-violet-50 sm:text-5xl">NFT Marketplace</h1>
              <p className="mt-3 text-sm leading-relaxed text-violet-100/75 sm:text-base">
                Discover, buy, and sell digital assets within the ExaEarn ecosystem.
              </p>
            </div>
            <div className="inline-flex h-14 w-14 items-center justify-center rounded-2xl border border-auric-400/60 bg-cosmic-900/70 text-auric-300 shadow-button-glow">
              <Store className="h-7 w-7" aria-hidden="true" />
            </div>
          </div>

          <div className="mt-5 grid gap-2 rounded-xl border border-emerald-300/20 bg-emerald-500/5 p-4 sm:grid-cols-2">
            <p className="flex items-center gap-2 text-sm text-emerald-100/85">
              <ShieldCheck className="h-4 w-4 text-emerald-300" aria-hidden="true" />
              Secure marketplace transactions with protected settlement flows.
            </p>
            <p className="flex items-center gap-2 text-sm text-emerald-100/85">
              <Lock className="h-4 w-4 text-emerald-300" aria-hidden="true" />
              Ownership verified on-chain and listings tracked transparently.
            </p>
          </div>
        </header>

        <div className="mt-6">
          <Tabs items={tabs} activeTab={activeTab} onChange={setActiveTab} />
        </div>

        {activeTab === "explore" ? (
          <>
            <div className="mt-6">
              <FilterBar
                search={search}
                onSearch={setSearch}
                category={category}
                onCategory={setCategory}
                priceCap={priceCap}
                onPriceCap={setPriceCap}
                sortBy={sortBy}
                onSortBy={setSortBy}
              />
            </div>
            <section className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
              {filteredExplore.map((item) => (
                <NFTCard key={item.id} item={item} actionLabel="Buy NFT" onAction={setSelectedForPurchase} />
              ))}
            </section>
          </>
        ) : null}

        {activeTab === "myNfts" ? (
          <section className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {myNfts.length ? (
              myNfts.map((item) => (
                <NFTCard key={item.id} item={item} actionLabel="List NFT" onAction={setSelectedForListing} showOwner />
              ))
            ) : (
              <div className="nft-card col-span-full rounded-2xl p-8 text-center text-violet-100/70">No NFTs owned yet.</div>
            )}
          </section>
        ) : null}

        {activeTab === "listed" ? (
          <section className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {listed.map((item) => (
              <NFTCard key={item.id} item={item} actionLabel="Buy NFT" onAction={setSelectedForPurchase} showOwner />
            ))}
          </section>
        ) : null}

        {activeTab === "activity" ? (
          <section className="mt-6 nft-card rounded-2xl p-5 sm:p-6">
            <div className="mb-4 flex items-center gap-2">
              <Activity className="h-5 w-5 text-auric-300" aria-hidden="true" />
              <h2 className="font-['Sora'] text-2xl font-semibold text-violet-50">Marketplace Activity</h2>
            </div>
            <div className="space-y-3">
              {activityFeed.map((item) => (
                <div key={item.id} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-violet-300/20 bg-cosmic-900/55 px-4 py-3">
                  <div>
                    <p className="text-sm font-semibold text-violet-50">{item.type} - {item.item}</p>
                    <p className="text-xs text-violet-100/65">{item.date}</p>
                  </div>
                  <p className="text-sm font-semibold text-auric-300">{item.amount}</p>
                </div>
              ))}
            </div>
          </section>
        ) : null}
      </section>

      {selectedForPurchase ? <PurchaseModal item={selectedForPurchase} onClose={() => setSelectedForPurchase(null)} /> : null}
      {selectedForListing ? <ListingModal item={selectedForListing} onClose={() => setSelectedForListing(null)} /> : null}
    </main>
  );
}

export default NFTMarketplace;
