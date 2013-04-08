<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:output
		method="html"
		encoding="utf-8"
		omit-xml-declaration="yes"
		doctype-system="about:legacy-compat"
	/>

	<xsl:template match="/">
		<html>
			<head>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
				<base href="http://{root/meta/domain}{root/meta/base}" />
				<title>Pajas XSLT example</title>
			</head>
			<body>
				<h1>
					<xsl:value-of select="root/content/title" />
				</h1>
			</body>
		</html>
	</xsl:template>

</xsl:stylesheet>
