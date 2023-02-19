const async = require('async')

function tryLoad (drupal, url, callback) {
  drupal.loadRestExport(url, {'paginated': false},
    (err, result) => {
      if (err) { return callback(err) }

      if (result.length) {
	return callback(null, result[0])
      }

      callback(null, null)
    }
  )
}

module.exports = function getUserFromMail (drupal, address, callback) {
  tryLoad(drupal, '/rest/user?mail=' + encodeURIComponent(address.address), (err, result) => {
    if (err || result) { return callback(err, result) }

    tryLoad(drupal, '/rest/user?alias=' + encodeURIComponent(address.address), (err, result) => {
      if (err || result) { return callback(err, result) }
    })
  })

}
