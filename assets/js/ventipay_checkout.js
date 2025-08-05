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
        style: {
          width: '70px',         
          height: 'auto',        
          maxHeight: '28px',  
          objectFit: 'contain',
          flexShrink: 0          
        }
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
