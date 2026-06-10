interface SparklineProps {
  /** Data points, plotted left → right; renders nothing when empty. */
  data: number[];
  width?: number;
  height?: number;
  stroke?: string;
  fill?: string;
}

/**
 * Minimal SVG sparkline (port of design primitives.jsx Sparkline).
 * Purely decorative — hidden from assistive technology.
 */
export function Sparkline({
  data,
  width = 96,
  height = 28,
  stroke = 'var(--ink)',
  fill = 'rgba(22, 35, 63, 0.08)',
}: SparklineProps) {
  if (data.length === 0) {
    return null;
  }
  const max = Math.max(...data);
  const min = Math.min(...data);
  const range = max - min || 1;
  const points = data.map((value, index) => {
    const x = data.length === 1 ? width : (index / (data.length - 1)) * width;
    const y = height - ((value - min) / range) * height * 0.85 - height * 0.075;
    return `${x.toFixed(1)},${y.toFixed(1)}`;
  });
  const path = `M${points.join(' L')}`;
  const fillPath = `${path} L${width},${height} L0,${height} Z`;
  return (
    <svg
      aria-hidden="true"
      width={width}
      height={height}
      viewBox={`0 0 ${width} ${height}`}
      className="block"
    >
      <path d={fillPath} fill={fill} />
      <path
        d={path}
        stroke={stroke}
        strokeWidth="1.5"
        fill="none"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}
