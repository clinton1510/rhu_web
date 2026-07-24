export type DeviceType = 'ios' | 'android' | 'desktop';

export function detectDevice(): DeviceType {
  if (typeof window === 'undefined') {
    return 'desktop';
  }

  const userAgent = navigator.userAgent.toLowerCase();
  const platform = navigator.platform?.toLowerCase() || '';

  // Check for iOS devices
  const isIOS = /iphone|ipad|ipod/.test(userAgent) || 
                (platform.includes('mac') && navigator.maxTouchPoints > 1);

  // Check for Android devices
  const isAndroid = /android/.test(userAgent);

  if (isIOS) {
    return 'ios';
  } else if (isAndroid) {
    return 'android';
  } else {
    return 'desktop';
  }
}

export function useDeviceType(): DeviceType {
  // You can enhance this with state and window resize listeners if needed
  return detectDevice();
}

// Platform-specific styles helper
export const platformStyles = {
  ios: {
    card: 'bg-white rounded-2xl shadow-sm border border-gray-100',
    button: 'rounded-full font-semibold',
    header: 'bg-gradient-to-b from-white to-gray-50 border-b border-gray-200',
    bottomBar: 'fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-lg border-t border-gray-200 safe-area-inset-bottom',
    statusCard: 'bg-white rounded-2xl p-4 shadow-sm border border-gray-100',
  },
  android: {
    card: 'bg-white rounded-xl shadow-md',
    button: 'rounded-lg font-medium',
    header: 'bg-white shadow-sm',
    bottomBar: 'fixed bottom-0 left-0 right-0 bg-white shadow-[0_-2px_8px_rgba(0,0,0,0.1)]',
    statusCard: 'bg-white rounded-xl p-4 shadow-md',
  },
  desktop: {
    card: 'bg-white rounded-lg shadow-sm border border-gray-200',
    button: 'rounded-lg font-semibold',
    header: 'bg-white border-b border-gray-200',
    bottomBar: '', // No bottom bar on desktop
    statusCard: 'bg-white rounded-lg p-6 shadow-sm border border-gray-200',
  },
};
