/** @type {import('stylelint').Config} */
export default {
  extends: [
    'stylelint-config-standard-scss',
    'stylelint-config-sass-guidelines'
  ],
  plugins: [
    'stylelint-order'
  ],
  rules: {
    'alpha-value-notation': 'number',
    'max-nesting-depth': 4,
    'selector-max-compound-selectors': 5,
    'order/properties-order': [
      [],
      { unspecified: 'bottomAlphabetical' }
    ],

    'scss/comment-no-empty': null,
    'selector-no-qualifying-type': null,
    'scss/percent-placeholder-pattern': null
  }
}
