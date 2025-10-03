const settings = window.wc.wcSettings.getSetting('ventipay_data', {})
const element = window.wp.element

const Label = () => (
  element.createElement(
    'div',
    {
      style: {
        width: '100%',
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        gap: '0.5rem'
      }
    },
    settings.title,
    element.createElement(
      'img',
      {
        src: settings.icon,
        ref: (el) => {
          if (el) {
            el.setAttribute(
              'style',
              ` height: 45px !important;
                width: 45px !important;
                max-height: none !important;
                max-width: none !important;
                vertical-align: middle !important;
                background-repeat: no-repeat !important;
                background-size: cover !important;
                shape-margin: 1rem !important;
              `
            )
          }
        },
      },
      null
    )
  )
)

const Content = () => (
  element.createElement(element.Fragment, {}, settings.description || '')
)

const Block_Gateway = {
  name: 'ventipay',
  label: Label(),
  content: Content(),
  edit: Content(),
  canMakePayment: () => true,
  ariaLabel: settings.title,
  supports: {
    features: settings.supports
  }
}

window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway)
