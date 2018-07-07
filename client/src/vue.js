import Vue from 'vue'
import $ from 'jquery'
import i18n from '@/i18n'
import urls from '@/urls'
import 'bootstrap-vue/dist/bootstrap-vue.css'
import dateFormat from 'date-fns/format'
import dateLocaleDE from 'date-fns/locale/de'


Vue.filter('dateFormat', (date, format) => {
  return dateFormat(date, format, { locale: dateLocaleDE })
})

Vue.filter('i18n', (key, variables = {}) => {
  console.warn(`i18n as a vue filter is deprecated. use i18n() as a vue functions`)
  return i18n(key, variables)
})
Vue.prototype.$i18n = (key, variables = {}) => {
  return i18n(key, variables)
}

Vue.prototype.$url = (key, ...params) => {
  return urls[key](...params)
}

export function vueUse (components) {
  $('.vue-wrapper').each((index, el) => {
    let elementName = $(el).data('element')
    let props = $(el).data('props') || {}

    if (!elementName) {
      throw new Error('fsvue-Error: missing element name. pass it as <div class="vue-wrapper" data-element="myelement" />')
    }

    // eslint-disable-next-line no-new
    let vm = new Vue({
      el,
      components,
      render (h) {
        return h(elementName)
      }
    })

    for (let key in props) {
      if (typeof vm.$children[0][key] === 'undefined' || typeof vm.$children[0][key] === 'function') {
        throw new Error(`vueUse() Error: prop '${key}' needs to be defined in data()`)
      }
      vm.$children[0][key] = props[key]
    }
  })
}
