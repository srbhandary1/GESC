export function canRenderAvatar3d(): boolean {
  if (
    typeof window === 'undefined' ||
    typeof document === 'undefined' ||
    typeof window.ResizeObserver !== 'function' ||
    typeof window.matchMedia !== 'function'
  ) {
    return false
  }

  try {
    const canvas = document.createElement('canvas')
    const context =
      canvas.getContext('webgl2') ?? canvas.getContext('webgl')

    return context !== null
  } catch {
    return false
  }
}
