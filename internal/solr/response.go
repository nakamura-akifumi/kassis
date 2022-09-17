package solr

import (
	"fmt"
	"strings"
)

type Response struct {
	Header *ResponseHeader `json:"responseHeader"`
	Data   *ResponseData   `json:"response"`
	Error  *ResponseError  `json:"error"`
	Doc    *Doc            `json:"doc"`
	Status *string         `json:"status"`
}

type ResponseHeader struct {
	Status int32                   `json:"status"`
	QTime  int32                   `json:"QTime"`
	Params *map[string]interface{} `json:"params"`
}

type ResponseData struct {
	NumFound int64 `json:"numFound"`
	Start    int64 `json:"start"`
	Docs     Docs  `json:"docs"`
}

type Docs []*Doc

type Doc map[string]interface{}

func (d Docs) ToBytes() ([]byte, error) {
	return interfaceToBytes(d)
}

func (d *Doc) ToBytes() ([]byte, error) {
	return interfaceToBytes(d)
}

type ResponseError struct {
	Code    float64  `json:"code"`
	Message string   `json:"msg"`
	Meta    []string `json:"metadata"`
	Details []string `json:"details"`
}

func (r *ResponseError) Error() string {
	if len(r.Details) > 0 {
		var msgs []string
		for _, detail := range r.Details {
			msgs = append(msgs, detail)
		}
		return fmt.Sprintf("%s: {%s}", r.Message, strings.Join(msgs, ", "))
	}
	return r.Message
}
