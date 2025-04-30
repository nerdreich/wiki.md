/* global localStorage */

function getStoreValue (key) {
  return JSON.parse(localStorage.getItem('wikimd') ?? '{}')[key]
}

function setStoreValue (key, value) {
  const values = JSON.parse(localStorage.getItem('wikimd') ?? '{}')
  values[key] = value
  localStorage.setItem('wikimd', JSON.stringify(values))
}

// -----------------------------------------------------------------------------

function rememberAuthor () {
  const form = document.querySelector('.page-edit form')
  if (form) {
    const author = getStoreValue('author')
    if (author) {
      document.getElementById('author').value = author
    }
    form.addEventListener('submit', (event) => {
      setStoreValue('author', document.getElementById('author').value)
    })
  }
}

// -----------------------------------------------------------------------------

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', rememberAuthor)
} else {
  rememberAuthor()
}
