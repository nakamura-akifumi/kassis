package solr

import (
	"encoding/json"
	"fmt"
	"strings"
)

func formatBasePath(uri, corename string) string {
	if strings.HasSuffix(uri, "/solr") {
		return fmt.Sprintf("%s/%s", uri, corename)
	}
	return fmt.Sprintf("%s/solr/%s", uri, corename)
}

func interfaceToBytes(a interface{}) ([]byte, error) {
	b, err := json.Marshal(a)
	if err != nil {
		return nil, err
	}
	return b, err
}
