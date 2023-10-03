<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;
use DTApi\Http\Requests\GetJobsRequest;
use DTApi\Http\Requests\CreateJobRequest;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param GetJobsRequest  $request
     * @return mixed
     */
    public function index(GetJobsRequest $request)
    {
        $user = $request->getAuthenticatedUser();
        if($user->isAdmin() || $user->isSuperAdmin())
        {
            $response = $this->repository->getAll($request);
        }else{
            $response = $user ? $this->repository->getUsersJobs($user->id) : null;
        }

        return response($response);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $job = $this->repository->with('translatorJobRel.user')->find($id);
        if (!$job) {
            return response()->json([
                'message' => 'Job not found',
            ], 404);
        }
        return response($job);
    }

      /**
     * @param CreateJobRequest $request
     * @return mixed
     */
    public function store(CreateJobRequest $request)
    {
        $data = $request->validated();

        $response = $this->repository->store($request->getAuthenticatedUser(), $data);

        return response($response);
    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $data = $request->validated();
        $response = $this->repository->updateJob($id, $data);

        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        $data = $request->validated();
        $response = $this->repository->storeJobEmail($data);

        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        $user_id = $request->get('user_id');
        if (!$user_id) {
            return null;
        }

        $response = $this->repository->getUsersJobsHistory($user_id, $request);

        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $data = $request->validated();
        $user = $request->getAuthenticatedUser();
        $response = $this->repository->acceptJob($data, $user);

        return response()->json($response);
    }

    public function acceptJobWithId(Request $request)
    {
        $job_id = $request->get('job_id');
        $user = $request->getAuthenticatedUser();
        $response = $this->repository->acceptJobWithId($job_id, $user);

        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        $data = $request->validated();
        $user = $request->getAuthenticatedUser();
        $response = $this->repository->cancelJobAjax($data, $user);

        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $data = $request->validated();
        $response = $this->repository->endJob($data);

        return response()->json($response);
    }

    public function customerNotCall(Request $request)
    {
        $data = $request->validated();
        $response = $this->repository->customerNotCall($data);

        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        $data = $request->validated();
        $user = $request->getAuthenticatedUser();
        $response = $this->repository->getPotentialJobs($user);

        return response()->json($response);
    }

    public function distanceFeed(Request $request)
    {
        $data = $request->validated();

        $distance = $data['distance'] ?? '';
        $time = $data['time'] ?? '';
        $jobid = $data['jobid'] ?? '';
        $session = $data['session_time'] ?? '';
        $flagged = $data['flagged'] === 'true' ? 'yes' : 'no';
        $manually_handled = $data['manually_handled'] === 'true' ? 'yes' : 'no';
        $by_admin = $data['by_admin'] === 'true' ? 'yes' : 'no';
        $admincomment = $data['admincomment'] ?? '';

        if ($time || $distance) {
            Distance::where('job_id', '=', $jobid)->update(compact('distance', 'time'));
        }

        if ($admincomment || $session || $flagged || $manually_handled || $by_admin) {
            Job::where('id', '=', $jobid)->update(compact('admin_comments', 'flagged', 'session_time', 'manually_handled', 'by_admin'));
        }

        return response('Record updated!');
    }

    public function reopen(Request $request)
    {
        $data = $request->validated();
        $response = $this->repository->reopen($data);

        return response($response);
    }

    public function resendNotifications(Request $request)
    {
        $data = $request->validated();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $job_data, '*');

        return response(['success' => 'Push sent']);
    }
    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $data = $request->validated();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);

            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['success' => $e->getMessage()]);
        }
    }

}
