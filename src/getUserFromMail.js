module.exports = function getUserFromMail (drupal, address, callback) {
  drupal.loadRestExport('/rest/user?mail=' + encodeURIComponent(address.address), {'paginated': false},
    (err, result) => {
      if (err) { return callback(err) }

      if (result.length) {
	return callback(null, result[0])
      }

      drupal.loadRestExport('/rest/user?alias=' + encodeURIComponent(address.address), {'paginated': false},
	(err, result) => {
	  if (err) { return callback(err) }

	  if (result.length) {
	    return callback(null, result[0])
	  }

	  return callback(null, null)
	}
      )
    }
  )
}
