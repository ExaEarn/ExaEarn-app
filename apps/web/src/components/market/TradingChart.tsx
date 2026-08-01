import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createChart, CandlestickSeries, HistogramSeries, LineSeries, AreaSeries, ColorType } from 'lightweight-charts';
import type { Candle } from '../../types/market';

type TradingChartType = 'candles' | 'line' | 'area';

type TradingChartProps = {
  candles: Candle[];
  chartType: TradingChartType;
  loading?: boolean;
  error?: string;
  heightClassName?: string;
  onRetry?: () => void;
};

const normalizeTime = (value: number) => {
  if (!Number.isFinite(value) || value <= 0) return 0;
  return value > 9_999_999_999 ? Math.floor(value / 1000) : Math.floor(value);
};

export default function TradingChart({
  candles,
  chartType,
  loading = false,
  error = '',
  heightClassName = 'h-[430px]',
  onRetry,
}: TradingChartProps) {
  const containerRef = useRef<HTMLDivElement | null>(null);
  const chartRef = useRef<any>(null);
  const mainSeriesRef = useRef<any>(null);
  const volumeSeriesRef = useRef<any>(null);
  const resizeObserverRef = useRef<ResizeObserver | null>(null);
  const [chartReady, setChartReady] = useState(false);

  const normalizedCandles = useMemo(() => candles
    .map((item) => ({
      time: normalizeTime(Number(item.time)),
      open: Number(item.open),
      high: Number(item.high),
      low: Number(item.low),
      close: Number(item.close),
      volume: Number(item.volume),
    }))
    .filter((item) => item.time > 0 && [item.open, item.high, item.low, item.close].every(Number.isFinite)), [candles]);

  useEffect(() => {
    const element = containerRef.current;
    if (!element || chartRef.current) return;

    const chart = createChart(element, {
      layout: {
        background: { type: ColorType.Solid, color: '#060a12' },
        textColor: '#94a3b8',
        attributionLogo: false,
      },
      grid: {
        vertLines: { color: 'rgba(148,163,184,0.06)' },
        horzLines: { color: 'rgba(148,163,184,0.06)' },
      },
      rightPriceScale: { borderColor: 'rgba(148,163,184,0.12)' },
      timeScale: { borderColor: 'rgba(148,163,184,0.12)', timeVisible: true, secondsVisible: false },
      crosshair: {
        vertLine: { color: 'rgba(245,158,11,0.25)' },
        horzLine: { color: 'rgba(245,158,11,0.25)' },
      },
      width: Math.max(element.clientWidth, 320),
      height: Math.max(element.clientHeight, 320),
    });

    const volumeSeries = chart.addSeries(HistogramSeries, {
      priceFormat: { type: 'volume' },
      priceScaleId: '',
      color: 'rgba(59,130,246,0.32)',
    });
    volumeSeries.priceScale().applyOptions({ scaleMargins: { top: 0.82, bottom: 0 } });

    chartRef.current = chart;
    volumeSeriesRef.current = volumeSeries;
    setChartReady(true);

    if (typeof ResizeObserver !== 'undefined') {
      resizeObserverRef.current = new ResizeObserver((entries) => {
        const entry = entries[0];
        if (!entry || !chartRef.current) return;
        const nextWidth = Math.max(Math.floor(entry.contentRect.width), 320);
        const nextHeight = Math.max(Math.floor(entry.contentRect.height), 320);
        chartRef.current.applyOptions({ width: nextWidth, height: nextHeight });
        chartRef.current.timeScale().fitContent();
      });
      resizeObserverRef.current.observe(element);
    }

    return () => {
      resizeObserverRef.current?.disconnect();
      resizeObserverRef.current = null;
      chart.remove();
      chartRef.current = null;
      mainSeriesRef.current = null;
      volumeSeriesRef.current = null;
      setChartReady(false);
    };
  }, []);

  useEffect(() => {
    if (!chartRef.current) return;
    if (mainSeriesRef.current) {
      chartRef.current.removeSeries(mainSeriesRef.current);
    }

    mainSeriesRef.current = chartType === 'candles'
      ? chartRef.current.addSeries(CandlestickSeries, {
        upColor: '#16a34a',
        downColor: '#ef4444',
        borderVisible: false,
        wickUpColor: '#16a34a',
        wickDownColor: '#ef4444',
        priceLineVisible: true,
        lastValueVisible: true,
      })
      : chartType === 'line'
        ? chartRef.current.addSeries(LineSeries, {
          color: '#f59e0b',
          lineWidth: 2,
          priceLineVisible: true,
          lastValueVisible: true,
        })
        : chartRef.current.addSeries(AreaSeries, {
          lineColor: '#f59e0b',
          topColor: 'rgba(245,158,11,0.28)',
          bottomColor: 'rgba(245,158,11,0.03)',
          lineWidth: 2,
          priceLineVisible: true,
          lastValueVisible: true,
        });
  }, [chartType]);

  useEffect(() => {
    if (!mainSeriesRef.current || !volumeSeriesRef.current || normalizedCandles.length === 0) return;

    if (chartType === 'candles') {
      mainSeriesRef.current.setData(normalizedCandles.map((item) => ({
        time: item.time,
        open: item.open,
        high: item.high,
        low: item.low,
        close: item.close,
      })));
    } else {
      mainSeriesRef.current.setData(normalizedCandles.map((item) => ({
        time: item.time,
        value: item.close,
      })));
    }

    volumeSeriesRef.current.setData(normalizedCandles.map((item) => ({
      time: item.time,
      value: item.volume,
      color: item.close >= item.open ? 'rgba(34,197,94,0.25)' : 'rgba(239,68,68,0.25)',
    })));

    chartRef.current.timeScale().fitContent();
  }, [normalizedCandles, chartType]);

  const showLoading = loading && normalizedCandles.length === 0;
  const showError = !showLoading && !!error && normalizedCandles.length === 0;
  const showEmpty = !showLoading && !showError && chartReady && normalizedCandles.length === 0;

  return (
    <div className={`relative mt-2 overflow-hidden rounded-xl border border-white/8 bg-[#050a12] ${heightClassName}`}>
      <div ref={containerRef} className="h-full w-full" />

      {showLoading ? (
        <div className="absolute inset-0 flex items-center justify-center bg-[#050a12]/88 text-sm text-slate-400">
          Loading chart...
        </div>
      ) : null}

      {showError ? (
        <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-[#050a12]/92 px-4 text-center">
          <p className="text-sm text-slate-300">Unable to load chart.</p>
          {onRetry ? (
            <button type="button" onClick={onRetry} className="rounded-lg bg-white/[0.06] px-3 py-2 text-xs font-medium text-slate-100">
              Retry
            </button>
          ) : null}
        </div>
      ) : null}

      {showEmpty ? (
        <div className="absolute inset-0 flex items-center justify-center bg-[#050a12]/80 text-sm text-slate-400">
          No chart data available.
        </div>
      ) : null}
    </div>
  );
}
