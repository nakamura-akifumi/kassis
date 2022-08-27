package kassiscore

import (
	"fmt"
	"regexp"
	"strings"
)

func InterfaceToStringArray(itfs []interface{}) ([]string, error) {
	var arr []string
	for _, s := range itfs {
		arr = append(arr, s.(string))
	}
	return arr, nil
}

func NormalizeISBN(s string) (string, error) {
	s = strings.TrimSpace(s)
	r := regexp.MustCompile(`[^0-9\-]`)
	if r.MatchString(s) == true {
		return s, fmt.Errorf("invalid character (allow 0-9 and -)")
	}
	r2 := regexp.MustCompile(`[0-9]+`)
	sa := r2.FindAllString(s, -1)
	s = strings.Join(sa, "")

	if len(s) != 10 && len(s) != 13 {
		return s, fmt.Errorf("invalid length (allow 10 or 13)")
	}

	return s, nil
}

// ArrayContains は、配列の中に特定の文字列が含まれるかを返す
func ArrayContains(arr []string, str string) bool {
	for _, v := range arr {
		if v == str {
			return true
		}
	}
	return false
}
