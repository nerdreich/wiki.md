function setup () {
  const textareas = document.querySelectorAll('.textarea-fancy')
  textareas.forEach((textarea) => {
    textarea.addEventListener('keydown', (keydown) => {
      if (fancyTextarea(keydown)) {
        keydown.stopPropagation()
        keydown.preventDefault()
      }
    })

    textarea.addEventListener('input', record)
    textarea.dispatchEvent(new Event('input')) // initial record
  })
}

function fancyTextarea (keydown) {
  const textarea = keydown.target
  switch (keydown.key) {
    case '(':
      return brackets(textarea, '(', ')')
    case '{':
      return brackets(textarea, '{', '}')
    case '[':
      return brackets(textarea, '[', ']')
    case 'z':
      if (keydown.ctrlKey) return undo(textarea)
      break
    case 'ArrowUp':
      if (keydown.ctrlKey) return move(textarea, -1)
      break
    case 'ArrowDown':
      if (keydown.ctrlKey) return move(textarea, +1)
      break
  }
  return false
}

// --- undo --------------------------------------------------------------------

const HISTORY_LENGTH = 128

function record (input) {
  const textarea = input.target
  textarea._history = textarea._history ?? []
  if (textarea._history.length <= 0) {
    textarea._history.push({
      content: textarea.value,
      start: textarea.selectionStart,
      end: textarea.selectionEnd,
      initial: true,
    })
  } else {
    if (textarea._history.length > HISTORY_LENGTH) textarea._history.shift()
    textarea._history.push({
      content: textarea.value,
      start: textarea.selectionStart,
      end: textarea.selectionEnd,
      initial: false,
    })
  }
}

function undo (textarea) {
  if (textarea._history.length > 1) {
    const current = textarea._history.pop() // discard current
    const previous = textarea._history.pop()
    if (previous) {
      textarea._history.push(previous)
      textarea.value = previous.content
      if (previous.initial) {
        const pos = getDiffPos(previous.content, current.content)
        textarea.setSelectionRange(pos, pos)
      } else {
        textarea.setSelectionRange(previous.start, previous.end)
      }
    }
  }
  return true
}

function getDiffPos (a, b) {
  let from = 0
  while (from < a.length && from < b.length) {
    if (a[from] !== b[from]) return from
    from++
  }
}

// --- move lines --------------------------------------------------------------

function move (textarea, direction) {
  const v = textarea.value
  const start = textarea.selectionStart
  const end = textarea.selectionEnd
  const lineFrom = (v.substring(0, start).match(/\n/g) ?? []).length
  const lineTo = (v.substring(0, end).replace(/\n$/, '').match(/\n/g) ?? [])
    .length
  const lines = (v.match(/\n/g) ?? []).length

  // nothing to do?
  if (direction < 0 && lineFrom <= 0) return true // already first
  if (direction > 0 && lineTo >= lines) return true // already last

  // swap lines
  const split = v.split(/\n/)
  const delta =
    direction < 0
      ? (split[lineFrom - 1].length + 1) * -1
      : split[lineTo + 1].length + 1
  const toMove = split.splice(lineFrom, lineTo - lineFrom + 1)
  split.splice(lineFrom + direction, 0, ...toMove)
  textarea.value = split.join('\n')

  // update cursor
  textarea.selectionStart = start + delta
  textarea.selectionEnd = end + delta

  return true
}

// --- auto-brackets -----------------------------------------------------------

function brackets (textarea, open, close) {
  const start = textarea.selectionStart
  const end = textarea.selectionEnd
  const v = textarea.value
  if (start !== end) {
    textarea.value = `${v.substring(0, start)}${open}${v.substring(start, end)}${close}${v.substring(end)}`
    textarea.setSelectionRange(start + 1, end + 1)
    textarea.dispatchEvent(new Event('input'))
    return true
  }
  return false
}

// -----------------------------------------------------------------------------

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', setup)
} else {
  setup()
}
