package solr

import (
	"fmt"
	"strings"
	"time"
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

type AdminResponse struct {
	Header       *ResponseHeader                `json:"responseHeader"`
	Error        *ResponseError                 `json:"error"`
	Status       map[string]*CoreStatusResponse `json:"status"`
	ReqStatus    string                         `json:"STATUS"`
	Response     interface{}                    `json:"response"`
	InitFailures interface{}                    `json:"initFailures"`
	Core         string                         `json:"core"`
}

type CoreStatusResponse struct {
	Name        string        `json:"name"`
	InstanceDir string        `json:"instanceDir"`
	DataDir     string        `json:"dataDir"`
	Config      string        `json:"config"`
	Schema      string        `json:"schema"`
	StartTime   time.Time     `json:"startTime"`
	Uptime      time.Duration `json:"uptime"`
	Index       *IndexData    `json:"index"`
}

/*
func (s *CoreStatusResponse) FindByCorename(corename string) *CoreStatusResponse {

}
*/

type IndexData struct {
	NumDocs                 int64     `json:"numDocs"`
	MaxDoc                  int64     `json:"maxDoc"`
	DeletedDocs             int64     `json:"deletedDocs"`
	IndexHeapUsageBytes     int64     `json:"indexHeapUsageBytes"`
	Version                 int64     `json:"version"`
	SegmentCount            int64     `json:"segmentCount"`
	Current                 bool      `json:"current"`
	HasDeletions            bool      `json:"hasDeletions"`
	Directory               string    `json:"directory"`
	SegmentsFile            string    `json:"segmentsFile"`
	SegmentsFileSizeInBytes int64     `json:"segmentsFileSizeInBytes"`
	UserData                *UserData `json:"userData"`
	LastModified            time.Time `json:"lastModified"`
	SizeInBytes             int64     `json:"sizeInBytes"`
	Size                    string    `json:"size"`
}

// UserData contains information about commits.
type UserData struct {
	CommitCommandVersion string `json:"commitCommandVer"`
	CommitTimeMSec       string `json:"commitTimeMSec"`
}
