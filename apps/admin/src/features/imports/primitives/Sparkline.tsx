export interface SparklineProps {
  data: ReadonlyArray<number>;
  width?: number;
  height?: number;
  stroke?: string;
  fill?: string;
  className?: string;
}

export function Sparkline({
  data,
  width = 96,
  height = 28,
  stroke = '#18181b',
  fill = 'rgba(24,24,27,0.08)',
  className,
}: SparklineProps) {
  if (data.length === 0) {
    return null;
  }
  const max = Math.max(...data);
  const min = Math.min(...data);
  const range = max - min || 1;
  const pts = data.map((v, i) => {
    const denominator = data.length - 1 || 1;
    const x = (i / denominator) * width;
    const y = height - ((v - min) / range) * height * 0.85 - height * 0.075;
    return `${x.toFixed(1)},${y.toFixed(1)}`;
  });
  const path = `M${pts.join(' L')}`;
  const fillPath = `${path} L${width},${height} L0,${height} Z`;
  return (
    <svg
      width={width}
      height={height}
      viewBox={`0 0 ${width} ${height}`}
      className={`block ${className ?? ''}`.trim()}
      role="img"
      aria-label="Sparkline"
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
