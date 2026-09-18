export interface RazorpayCheckoutRequest {
  readonly key: string
  readonly orderId: string
  readonly amount: number
  readonly currency: 'INR'
  readonly name: string
  readonly description: string
}

export interface RazorpayCheckoutSuccess {
  readonly paymentId: string
  readonly orderId: string
  readonly signature: string
}

interface RazorpayResponse {
  readonly razorpay_payment_id: string
  readonly razorpay_order_id: string
  readonly razorpay_signature: string
}

interface RazorpayInstance {
  open(): void
}

interface RazorpayConstructor {
  new (options: Record<string, unknown>): RazorpayInstance
}

declare global {
  interface Window {
    Razorpay?: RazorpayConstructor
  }
}

let loader: Promise<void> | null = null

function loadRazorpay(): Promise<void> {
  if (window.Razorpay !== undefined) return Promise.resolve()
  if (loader !== null) return loader

  loader = new Promise<void>((resolve, reject) => {
    const script = document.createElement('script')
    script.src = 'https://checkout.razorpay.com/v1/checkout.js'
    script.async = true
    script.dataset.globalEnglishRazorpay = 'true'
    script.addEventListener('load', () => {
      if (window.Razorpay === undefined) {
        reject(new Error('razorpay_unavailable'))
        return
      }
      resolve()
    }, { once: true })
    script.addEventListener('error', () => reject(new Error('razorpay_unavailable')), {
      once: true,
    })
    document.head.append(script)
  }).catch((cause: unknown) => {
    loader = null
    throw cause
  })

  return loader
}

export async function openRazorpayCheckout(
  request: RazorpayCheckoutRequest,
): Promise<RazorpayCheckoutSuccess | null> {
  await loadRazorpay()
  const Constructor = window.Razorpay
  if (Constructor === undefined) throw new Error('razorpay_unavailable')

  return new Promise((resolve, reject) => {
    let settled = false
    const checkout = new Constructor({
      amount: request.amount,
      currency: request.currency,
      description: request.description,
      handler: (response: RazorpayResponse) => {
        if (settled) return
        settled = true
        resolve({
          paymentId: response.razorpay_payment_id,
          orderId: response.razorpay_order_id,
          signature: response.razorpay_signature,
        })
      },
      key: request.key,
      modal: {
        ondismiss: () => {
          if (settled) return
          settled = true
          resolve(null)
        },
      },
      name: request.name,
      order_id: request.orderId,
      retry: { enabled: false },
      theme: { color: '#006b3f' },
    })

    try {
      checkout.open()
    } catch (cause) {
      reject(cause)
    }
  })
}
