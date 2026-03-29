document.addEventListener('keydown', (keydown) => hotkeys(keydown))

function hotkeys (keydown) {
  if (hotkeysView(keydown) || hotkeysEdit(keydown)) {
    keydown.stopPropagation()
    keydown.preventDefault()
  }
}

// --- viewer ------------------------------------------------------------------

function hotkeysView (keydown) {
  const form = document.querySelector('.page-view')
  if (form) {
    switch (keydown.key) {
      case 'e':
        if (keydown.ctrlKey) return viewEdit()
    }
  }
  return false
}

function viewEdit () {
  document.querySelector('.nav-edit')?.click()
}

// --- editor ------------------------------------------------------------------

function hotkeysEdit (keydown) {
  const form = document.querySelector('.page-edit form')
  if (form) {
    switch (keydown.key) {
      case 's':
        if (keydown.ctrlKey) return editSave()
        break
      case 'Escape':
        return editCancel()
    }
  }
  return false
}

function editSave () {
  document.querySelector('.page-edit input.primary')?.click()
  return true
}

function editCancel (keydown) {
  window.history.go(-1)
  return true
}
